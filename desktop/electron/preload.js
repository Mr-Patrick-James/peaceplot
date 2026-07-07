// Preload script — runs in renderer context with limited Node access.
// Exposes only what the app needs through contextBridge.
const { contextBridge } = require('electron');

contextBridge.exposeInMainWorld('peaceplot', {
  version: process.env.npm_package_version || '1.0.0',
  platform: process.platform
});
