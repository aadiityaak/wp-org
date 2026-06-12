const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const version = '0.1.0';
const distDir = path.join(__dirname, '..', 'dist');
const zipPath = path.join(distDir, `wp-org-${version.replace(/\./g, '-')}.zip`);
const pluginDir = path.join(__dirname, '..');

if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

if (fs.existsSync(zipPath)) {
  fs.unlinkSync(zipPath);
}

const output = fs.createWriteStream(zipPath);
const archive = archiver('zip', { zlib: { level: 9 } });

output.on('close', () => {
  const size = (archive.pointer() / 1024).toFixed(1);
  console.log(`Created ${path.basename(zipPath)} (${size} KB)`);
});

archive.on('error', (err) => {
  throw err;
});

archive.pipe(output);

const files = [
  'wp-org.php',
  'composer.json',
  'composer.lock',
];

const dirs = [
  'src',
  'assets',
  'data',
];

files.forEach((file) => {
  const filePath = path.join(pluginDir, file);
  if (fs.existsSync(filePath)) {
    archive.file(filePath, { name: `wp-org/${file}` });
  }
});

dirs.forEach((dir) => {
  const dirPath = path.join(pluginDir, dir);
  if (fs.existsSync(dirPath)) {
    archive.directory(dirPath, `wp-org/${dir}`);
  }
});

const vendorPath = path.join(pluginDir, 'vendor');
if (fs.existsSync(vendorPath)) {
  archive.directory(vendorPath, 'wp-org/vendor');
}

archive.finalize();