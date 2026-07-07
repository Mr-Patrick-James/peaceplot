const { app, BrowserWindow, dialog, shell, Tray, Menu, nativeImage } = require('electron');
const path = require('path');
const { spawn } = require('child_process');
const net = require('net');
const fs = require('fs');

let mainWindow = null;
let phpProcess = null;
let tray = null;
const PHP_PORT = 9876;

// ─── Resolve paths depending on dev vs packaged ───────────────────────────────
function getResourcePath(...parts) {
  if (app.isPackaged) {
    return path.join(process.resourcesPath, ...parts);
  }
  return path.join(__dirname, '..', ...parts);
}

const phpBin  = getResourcePath('php', 'php.exe');
const wwwRoot = getResourcePath('www');

// ─── Find a free port ─────────────────────────────────────────────────────────
function findFreePort(startPort) {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.listen(startPort, '127.0.0.1', () => {
      const port = server.address().port;
      server.close(() => resolve(port));
    });
    server.on('error', () => findFreePort(startPort + 1).then(resolve).catch(reject));
  });
}

// ─── Start PHP built-in server ────────────────────────────────────────────────
async function startPHP(port) {
  return new Promise((resolve, reject) => {
    if (!fs.existsSync(phpBin)) {
      reject(new Error(`PHP not found at: ${phpBin}\n\nPlease ensure the php/ folder is present.`));
      return;
    }

    const args = [
      '-S', `127.0.0.1:${port}`,
      '-t', wwwRoot,
      path.join(wwwRoot, 'router.php')
    ];

    phpProcess = spawn(phpBin, args, {
      cwd: wwwRoot,
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe']
    });

    phpProcess.on('error', (err) => reject(err));

    // Give PHP a moment to start, then resolve
    setTimeout(() => resolve(port), 1200);

    phpProcess.stderr.on('data', (data) => {
      // PHP dev server logs are on stderr — that's normal
      console.log('[PHP]', data.toString());
    });

    phpProcess.on('exit', (code) => {
      console.log(`PHP process exited with code ${code}`);
    });
  });
}

// ─── Create the main Electron window ─────────────────────────────────────────
function createWindow(port) {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 1024,
    minHeight: 680,
    title: 'PeacePlot — Cemetery Management System',
    icon: path.join(__dirname, '..', 'build', 'icon.ico'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      webSecurity: true
    },
    backgroundColor: '#0e1f35',
    show: false // show once ready to avoid flash
  });

  // Load the app
  mainWindow.loadURL(`http://127.0.0.1:${port}/index.php`);

  // Show when content is ready
  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });

  // Handle failed loads (PHP not ready yet) — retry after delay
  mainWindow.webContents.on('did-fail-load', () => {
    setTimeout(() => {
      mainWindow.loadURL(`http://127.0.0.1:${port}/index.php`);
    }, 1000);
  });

  // Open external links in default browser, not Electron
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  // Hide to tray instead of closing
  mainWindow.on('close', (event) => {
    if (!app.isQuitting) {
      event.preventDefault();
      mainWindow.hide();
    }
  });
}

// ─── System tray ──────────────────────────────────────────────────────────────
function createTray(port) {
  const iconPath = path.join(__dirname, '..', 'build', 'icon.ico');
  const icon = fs.existsSync(iconPath)
    ? nativeImage.createFromPath(iconPath)
    : nativeImage.createEmpty();

  tray = new Tray(icon);
  tray.setToolTip('PeacePlot — Cemetery Management System');

  const contextMenu = Menu.buildFromTemplate([
    {
      label: 'Open PeacePlot',
      click: () => {
        if (mainWindow) mainWindow.show();
        else createWindow(port);
      }
    },
    { type: 'separator' },
    {
      label: 'Quit',
      click: () => {
        app.isQuitting = true;
        app.quit();
      }
    }
  ]);

  tray.setContextMenu(contextMenu);
  tray.on('double-click', () => {
    if (mainWindow) mainWindow.show();
  });
}

// ─── App lifecycle ────────────────────────────────────────────────────────────
app.whenReady().then(async () => {
  try {
    const port = await findFreePort(PHP_PORT);
    await startPHP(port);
    createTray(port);
    createWindow(port);
  } catch (err) {
    dialog.showErrorBox('PeacePlot — Startup Error', err.message);
    app.quit();
  }
});

app.on('window-all-closed', () => {
  // Keep app running in tray on Windows
  if (process.platform !== 'darwin') {
    // Don't quit — let tray handle it
  }
});

app.on('activate', () => {
  if (mainWindow === null) {
    findFreePort(PHP_PORT).then((port) => createWindow(port));
  } else {
    mainWindow.show();
  }
});

app.on('before-quit', () => {
  app.isQuitting = true;
  if (phpProcess) {
    phpProcess.kill('SIGTERM');
    phpProcess = null;
  }
  if (tray) {
    tray.destroy();
  }
});
