const { app, BrowserWindow, Menu, ipcMain, shell, dialog, Notification, session } = require('electron');
const path = require('path');
const http = require('http');

let mainWindow = null;
let isForceClosing = false;

const DEFAULT_CONFIG = {
    title: 'SIDBM Next Desktop',
    width: 1440,
    height: 900,
    minWidth: 1024,
    minHeight: 700,
    url: process.env.APP_URL 
        ? (process.env.APP_URL.includes('/login') ? process.env.APP_URL : `${process.env.APP_URL.replace(/\/$/, '')}/login`)
        : 'http://127.0.0.1:8000/login',
};

// Set App User Model ID for Windows Action Center Notifications
if (process.platform === 'win32') {
    app.setAppUserModelId('com.enpiistudio.sidbm.desktop');
}

function createWindow() {
    mainWindow = new BrowserWindow({
        title: DEFAULT_CONFIG.title,
        width: DEFAULT_CONFIG.width,
        height: DEFAULT_CONFIG.height,
        minWidth: DEFAULT_CONFIG.minWidth,
        minHeight: DEFAULT_CONFIG.minHeight,
        frame: false, // Frameless window (custom app bar)
        titleBarStyle: 'hidden',
        show: false,
        backgroundColor: '#0f172a',
        webPreferences: {
            preload: path.join(__dirname, 'preload.cjs'),
            nodeIntegration: false,
            contextIsolation: true,
            sandbox: false,
        },
    });

    // Attach desktop client identification header to all requests
    session.defaultSession.webRequest.onBeforeSendHeaders((details, callback) => {
        details.requestHeaders['X-Desktop-Client'] = '1';
        callback({ cancel: false, requestHeaders: details.requestHeaders });
    });

    let appUrl = process.env.APP_URL || DEFAULT_CONFIG.url;
    if (!appUrl.includes('/login') && !appUrl.includes('/dashboard')) {
        appUrl = `${appUrl.replace(/\/$/, '')}/login`;
    }
    mainWindow.loadURL(appUrl);

    mainWindow.once('ready-to-show', () => {
        mainWindow.show();
    });

    mainWindow.on('maximize', () => {
        mainWindow?.webContents.send('desktop:maximize-change', true);
    });

    mainWindow.on('unmaximize', () => {
        mainWindow?.webContents.send('desktop:maximize-change', false);
    });

    // Intercept native close to allow graceful logout
    mainWindow.on('close', (event) => {
        if (!isForceClosing) {
            event.preventDefault();
            mainWindow.webContents.send('desktop:window-close-requested');

            // Safety timeout to force close if renderer hangs
            setTimeout(() => {
                isForceClosing = true;
                if (mainWindow && !mainWindow.isDestroyed()) {
                    mainWindow.destroy();
                }
            }, 1800);
        }
    });

    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (url.startsWith('http:') || url.startsWith('https:')) {
            shell.openExternal(url);
            return { action: 'deny' };
        }
        return { action: 'allow' };
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });

    // Remove native application menu bar
    Menu.setApplicationMenu(null);
}

// Window Control IPC Handlers
ipcMain.on('desktop:window-minimize', () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.minimize();
    }
});

ipcMain.on('desktop:window-maximize', () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
        if (mainWindow.isMaximized()) {
            mainWindow.unmaximize();
        } else {
            mainWindow.maximize();
        }
    }
});

ipcMain.on('desktop:window-close', () => {
    isForceClosing = true;
    if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.destroy();
    }
});

ipcMain.handle('desktop:is-maximized', () => {
    return mainWindow ? mainWindow.isMaximized() : false;
});

// Native OS Push Notification Handler (Zero Firebase required)
ipcMain.on('desktop:send-notification', (_, { title, body, icon, url }) => {
    if (Notification.isSupported()) {
        const iconPath = icon ? path.resolve(icon) : path.join(__dirname, '../public/favicon.ico');
        const notif = new Notification({
            title: title || 'SIDBM Next Desktop',
            body: body || '',
            icon: iconPath,
            silent: false,
        });

        if (url) {
            notif.on('click', () => {
                if (mainWindow) {
                    if (mainWindow.isMinimized()) mainWindow.restore();
                    mainWindow.show();
                    mainWindow.focus();
                    if (url.startsWith('/')) {
                        mainWindow.webContents.send('desktop:navigate', url);
                    }
                }
            });
        }

        notif.show();
    }
});

ipcMain.handle('desktop:get-info', () => {
    return {
        platform: process.platform,
        arch: process.arch,
        version: app.getVersion(),
        isDesktop: true,
        electronVersion: process.versions.electron,
        nodeVersion: process.versions.node,
        notificationsSupported: Notification.isSupported(),
    };
});

ipcMain.handle('desktop:check-connectivity', async (_, urlToCheck) => {
    const target = urlToCheck || 'https://www.google.com';
    return new Promise((resolve) => {
        try {
            const req = http.get(target, (res) => {
                resolve(res.statusCode >= 200 && res.statusCode < 400);
            });
            req.on('error', () => resolve(false));
            req.setTimeout(4000, () => {
                req.destroy();
                resolve(false);
            });
        } catch {
            resolve(false);
        }
    });
});

app.whenReady().then(() => {
    createWindow();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createWindow();
        }
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});