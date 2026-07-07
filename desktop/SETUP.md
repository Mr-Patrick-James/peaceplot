# PeacePlot Desktop — Setup Guide

## Folder Structure

```
desktop/
├── electron/
│   ├── main.js          ← Electron app entry point
│   └── preload.js       ← Context bridge
├── www/                 ← Your PeacePlot web app (copy here)
│   └── router.php       ← PHP built-in server router
├── php/                 ← PHP runtime (you download this)
│   └── php.exe
├── build/
│   └── icon.ico         ← App icon
├── dist/                ← Built installer goes here (auto-created)
├── package.json
└── SETUP.md
```

---

## Step 1 — Install Node.jsru

Download and install Node.js (LTS) from: https://nodejs.org

Verify installation:
```
node --version
npm --version
```

---

## Step 2 — Download Portable PHP

1. Go to: https://windows.php.net/download/
2. Download **PHP 8.x (Non-Thread Safe) ZIP** (x64)
3. Extract it and rename the folder to `php`
4. Place the entire `php` folder inside this `desktop/` directory
5. Inside the `php/` folder, copy `php.ini-production` → rename it to `php.ini`
6. Open `php.ini` and make sure these extensions are enabled (remove the `;` at the start):
   ```
   extension=pdo_sqlite
   extension=sqlite3
   extension=fileinfo
   extension=gd
   ```

---

## Step 3 — Copy PeacePlot Web App

Copy the entire contents of your `peaceplot/` project into `desktop/www/`:

```
desktop/www/
├── router.php        ← already here, keep it
├── index.php         ← from peaceplot root
├── public/
├── api/
├── assets/
├── config/
├── database/
└── ...
```

> Make sure the `database/peaceplot.db` file is included — that's your data!

---

## Step 4 — Add App Icon

Place a `icon.ico` file in `desktop/build/`.

You can convert a PNG to ICO at: https://convertico.com
Recommended size: 256x256 pixels.

---

## Step 5 — Install Dependencies

Open a terminal in the `desktop/` folder:

```bash
npm install
```

---

## Step 6 — Test the App (Dev Mode)

```bash
npm start
```

This opens PeacePlot as a desktop window. Test that everything works correctly.

---

## Step 7 — Build the Installer (.exe)

```bash
npm run build
```

The installer will be created in `desktop/dist/`:
```
dist/
└── PeacePlot Setup 1.0.0.exe   ← Share this!
```

---

## Distribution

Send the `PeacePlot Setup 1.0.0.exe` file to users. When they run it:
1. A standard Windows installer opens
2. They choose installation folder
3. A desktop shortcut is created
4. The app runs offline — no browser, no WAMP needed

---

## Notes

- The app runs a local PHP server on port `9876` (auto-picks another port if busy)
- All data stays in `database/peaceplot.db` inside the install folder
- The app appears in the system tray when minimized
- Double-click the tray icon to restore the window
