/* eslint-env node */
const gulp = require('gulp');
const sourcemaps = require('gulp-sourcemaps');
const plumber = require('gulp-plumber');
const { Transform } = require('stream');
const { spawnSync } = require('node:child_process');
const path = require('path');
const fs = require('fs');

const DIST = 'dist';
// Migrated: SRC was 'src' -> now 'scss' as SCSS source root per migration.
const SRC = 'scss';
const BUNDLE_DIR = 'bundled';
const PACKAGE_FOLDER = 'aivec-ai-search-schema';
const PLUGIN_FILE = 'aivec-ai-search-schema.php';

const paths = {
  scss: `${SRC}/**/*.scss`,
  copy: [
    'aivec-ai-search-schema.php',
    'includes/**/*',
    'src/**/*',
    'templates/**/*',
    'assets/**/*',
    'composer.json',
    'package.json',
    'package-lock.json',
    'readme.txt',
    'LICENSE*'
  ]
};

function clean() {
  // `del` became ESM-only; defer import to keep gulpfile in CommonJS.
  return import('del').then(({ deleteAsync }) => deleteAsync([DIST]));
}

function renameToMinCss() {
  return new Transform({
    objectMode: true,
    transform(file, _, cb) {
      const parsed = path.parse(file.path);
      const baseName = parsed.name.endsWith('.min') ? parsed.name : `${parsed.name}.min`;
      file.path = path.join(parsed.dir, `${baseName}${parsed.ext}`);
      cb(null, file);
    }
  });
}

function prefixDistFolder(folderName) {
  return new Transform({
    objectMode: true,
    transform(file, _, cb) {
      const relative = path.relative(file.base, file.path);
      file.path = path.join(file.base, folderName, relative);
      cb(null, file);
    }
  });
}

function scss() {
  // Sass CLI (dart-sass) を直接呼び出す。legacy JS API の非推奨を回避するため。
  const outDir = path.resolve('assets/dist/css');
  fs.mkdirSync(outDir, { recursive: true });

  const sassScript = path.resolve('node_modules/sass/sass.js');
  const sassExists = fs.existsSync(sassScript);

  // List of SCSS files to compile
  const scssFiles = ['admin', 'wizard'];
  const outputFiles = [];

  for (const name of scssFiles) {
    const entry = path.resolve(`${SRC}/${name}.scss`);
    const outFile = path.join(outDir, `${name}.min.css`);

    if (!fs.existsSync(entry)) {
      continue;
    }

    const result = spawnSync(
      process.execPath,
      [
        sassExists ? sassScript : 'node_modules/sass/sass.js',
        '--style=compressed',
        '--no-error-css',
        '--embed-source-map',
        '--embed-sources',
        entry,
        outFile,
      ],
      { stdio: 'inherit' }
    );

    if (result.status !== 0) {
      throw new Error(`Sass build failed for ${name}.scss`);
    }

    outputFiles.push(outFile, `${outFile}.map`);
  }

  if (outputFiles.length === 0) {
    return gulp.src(`${SRC}/*.scss`, { allowEmpty: true });
  }

  // sourcemaps は Sass CLI 側で生成されるため、ここではファイルの配置のみ確認。
  return gulp.src(outputFiles, { allowEmpty: true })
    .pipe(gulp.dest('assets/dist/css'));
}

function copy() {
  const baseGlobs = paths.copy.filter((pattern) => {
    if (pattern.endsWith('/**/*')) {
      const dir = pattern.slice(0, -('/**/*'.length));
      return fs.existsSync(dir);
    }
    return true;
  });

  const excludes = [
    '!node_modules/**',
    `!${DIST}/**`,
    '!bundled/**',
    '!tests/**',
    '!vendor/**',
    '!.git/**',
    '!.github/**',
    // Exclude WordPress.org directory assets (icons, screenshots, banners)
    '!assets/icon-*.png',
    '!assets/screenshot-*.png',
    '!assets/banner-*.png'
  ];

  return gulp.src(
    [...baseGlobs, ...excludes],
    {
      base: '.',
      allowEmpty: true,
      encoding: false, // keep binary assets (e.g. .mo) intact
    }
  )
    .pipe(gulp.dest(DIST));
}

function copyLanguagesDir() {
  const source = path.resolve('languages');
  const target = path.resolve(DIST, 'languages');
  const allowedExts = new Set(['.po', '.pot', '.mo', '.json']);

  if (!fs.existsSync(source)) {
    return;
  }

  fs.rmSync(target, { recursive: true, force: true });
  const copyRecursive = (srcDir, destDir) => {
    fs.mkdirSync(destDir, { recursive: true });
    const entries = fs.readdirSync(srcDir, { withFileTypes: true });
    entries.forEach((entry) => {
      const srcPath = path.join(srcDir, entry.name);
      const destPath = path.join(destDir, entry.name);
      if (entry.isDirectory()) {
        copyRecursive(srcPath, destPath);
        return;
      }
      if (allowedExts.has(path.extname(entry.name))) {
        fs.copyFileSync(srcPath, destPath);
      }
    });
  };

  copyRecursive(source, target);
}

function resolveGitVersionInfo() {
  const tagResult = spawnSync('git', ['describe', '--tags', '--abbrev=0', '--match', 'v[0-9]*.[0-9]*.[0-9]*'], { encoding: 'utf8' });
  const headResult = spawnSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' });
  const headShortResult = spawnSync('git', ['rev-parse', '--short', 'HEAD'], { encoding: 'utf8' });

  const tag = tagResult.status === 0 ? tagResult.stdout.trim() : '';
  const headFull = headResult.status === 0 ? headResult.stdout.trim() : '';
  const headShort = headShortResult.status === 0 ? headShortResult.stdout.trim() : '';

  const packageJsonPath = path.resolve('package.json');
  let packageVersion = '0.0.0';
  if (fs.existsSync(packageJsonPath)) {
    try {
      const parsed = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
      if (parsed && typeof parsed.version === 'string' && parsed.version.trim()) {
        packageVersion = parsed.version.trim();
      }
    } catch (error) {
      console.warn('[build] package.json のパースに失敗しました。', error);
    }
  }

  let baseVersion = '';
  let label = '';

  if (tag) {
    baseVersion = tag.replace(/^v/, '');
    const tagCommitResult = spawnSync('git', ['rev-list', '-n', '1', tag], { encoding: 'utf8' });
    const tagCommit = tagCommitResult.status === 0 ? tagCommitResult.stdout.trim() : '';
    if (tagCommit && headFull && tagCommit !== headFull && headShort) {
      label = `${tag}-${headShort}`;
    } else {
      label = tag;
    }
  } else {
    baseVersion = packageVersion;
    label = headShort ? `v${packageVersion}-${headShort}` : `v${packageVersion}`;
  }

  return {
    tag: tag || null,
    baseVersion,
    label,
    headShort: headShort || null
  };
}

function updatePluginFileVersion(version) {
  const pluginPath = path.resolve(PLUGIN_FILE);
  if (!fs.existsSync(pluginPath)) return;

  const contents = fs.readFileSync(pluginPath, 'utf8');
  const headerRegex = /(\*\s*Version:\s*)([^\r\n]+)/;
  const constRegex = /(define\(\s*['"]AVC_AIS_VERSION['"]\s*,\s*['"])([^'"]+)(['"]\s*\);)/;

  let updated = contents;
  if (headerRegex.test(updated)) {
    updated = updated.replace(headerRegex, (_, prefix) => `${prefix}${version}`);
  }
  if (constRegex.test(updated)) {
    updated = updated.replace(constRegex, (_, prefix, _old, suffix) => `${prefix}${version}${suffix}`);
  }

  if (updated !== contents) {
    fs.writeFileSync(pluginPath, updated);
  }
}

function updatePackageJsonVersion(version) {
  const pkgPath = path.resolve('package.json');
  if (!fs.existsSync(pkgPath)) return;

  const raw = fs.readFileSync(pkgPath, 'utf8');
  let data;
  try {
    data = JSON.parse(raw);
  } catch (error) {
    console.warn('[build] package.json のパースに失敗しました。', error);
    return;
  }

  if (data.version === version) return;
  data.version = version;
  fs.writeFileSync(pkgPath, `${JSON.stringify(data, null, 2)}\n`);
}

function updatePoHeaders(version) {
  const languagesDir = path.resolve('languages');
  if (!fs.existsSync(languagesDir)) {
    return;
  }

  const files = fs.readdirSync(languagesDir);
  const regex = /(Project-Id-Version:\s*)([^\n]*)(\\n)/;
  files.forEach((file) => {
    if (!file.endsWith('.po') && !file.endsWith('.pot')) {
      return;
    }

    const filePath = path.join(languagesDir, file);
    const original = fs.readFileSync(filePath, 'utf8');
    if (!regex.test(original)) {
      return;
    }

    const updated = original.replace(regex, `$1AI Search Schema ${version}$3`);
    if (updated !== original) {
      fs.writeFileSync(filePath, updated);
    }
  });
}

function syncVersionMetadata() {
  const { baseVersion } = resolveGitVersionInfo();
  updatePluginFileVersion(baseVersion);
  updatePackageJsonVersion(baseVersion);
  updatePoHeaders(baseVersion);
  return baseVersion;
}

function resolveVersionLabel() {
  return resolveGitVersionInfo().label;
}

function generateMetadata() {
  const distDir = path.resolve(DIST);
  fs.mkdirSync(distDir, { recursive: true });

  const version = syncVersionMetadata();
  const year = new Date().getFullYear();

  const licenseText = [
    'AI Search Schema',
    `Copyright (c) ${year} Aivec LLC contributors`,
    '',
    'This program is free software; you can redistribute it and/or modify',
    'it under the terms of the GNU General Public License as published by',
    'the Free Software Foundation; either version 2 of the License, or',
    '(at your option) any later version.',
    '',
    'This program is distributed in the hope that it will be useful,',
    'but WITHOUT ANY WARRANTY; without even the implied warranty of',
    'MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the',
    'GNU General Public License for more details.',
    '',
    'You should have received a copy of the GNU General Public License',
    'along with this program. If not, see https://www.gnu.org/licenses/gpl-2.0.html.',
    ''
  ].join('\n');

  fs.writeFileSync(path.join(distDir, 'LICENSE.txt'), `${licenseText}`);

  const distReadmePath = path.join(distDir, 'readme.txt');
  const sourceReadmePath = path.resolve('readme.txt');
  let readmeSource = '';

  if (fs.existsSync(distReadmePath)) {
    readmeSource = fs.readFileSync(distReadmePath, 'utf8');
  } else if (fs.existsSync(sourceReadmePath)) {
    readmeSource = fs.readFileSync(sourceReadmePath, 'utf8');
  }

  if (readmeSource) {
    const stableRegex = /(^Stable tag:\s*)(.*)$/im;
    const updatedReadme = stableRegex.test(readmeSource)
      ? readmeSource.replace(stableRegex, `$1${version}`)
      : `Stable tag: ${version}\n\n${readmeSource}`;

    const releaseNotesDir = path.resolve('docs', 'release-notes');
    let changelogEntry = '';

    if (fs.existsSync(releaseNotesDir)) {
      const noteFiles = fs.readdirSync(releaseNotesDir)
        .filter((file) => /^v\d+\.\d+\.\d+\.md$/i.test(file))
        .map((file) => {
          const match = file.match(/v(\d+\.\d+\.\d+)\.md/i);
          return match ? { version: match[1], file } : null;
        })
        .filter(Boolean)
        .sort((a, b) => {
          const [amaj, amin, apatch] = a.version.split('.').map(Number);
          const [bmaj, bmin, bpatch] = b.version.split('.').map(Number);
          if (amaj !== bmaj) return bmaj - amaj;
          if (amin !== bmin) return bmin - amin;
          return bpatch - apatch;
        });

      if (noteFiles.length > 0) {
        const latest = noteFiles[0];
        const notePath = path.join(releaseNotesDir, latest.file);
        const rawNote = fs.readFileSync(notePath, 'utf8');
        const sections = [];
        let currentSection = null;

        rawNote.split(/\r?\n/).forEach((line) => {
          const headingMatch = line.match(/^##\s+(.+)/);
          if (headingMatch) {
            if (currentSection && currentSection.items.length > 0) {
              sections.push(currentSection);
            }
            currentSection = { title: headingMatch[1].trim(), items: [] };
            return;
          }

          const itemMatch = line.match(/^[-*]\s+(.+)/);
          if (itemMatch && currentSection) {
            currentSection.items.push(itemMatch[1].trim());
          }
        });

        if (currentSection && currentSection.items.length > 0) {
          sections.push(currentSection);
        }

        if (sections.length > 0) {
          const entryLines = [`= ${latest.version} =`];
          sections.forEach((section) => {
            entryLines.push(`* ${section.title}`);
            section.items.forEach((item) => {
              entryLines.push(`  * ${item}`);
            });
          });
          changelogEntry = entryLines.join('\n');
        }
      }
    }

    let finalReadme = updatedReadme;

    if (changelogEntry) {
      const changelogRegex = /(==\s*Changelog\s*==)([\s\S]*?)(\n==\s*Upgrade Notice\s*==)/i;
      const changelogMatch = updatedReadme.match(changelogRegex);
      if (changelogMatch) {
        const existingChangelog = changelogMatch[2];
        if (!existingChangelog.includes(`= ${version} =`)) {
          const trimmedExisting = existingChangelog.trimStart().trimEnd();
          const insertion = `${changelogMatch[1]}\n${changelogEntry}\n\n${trimmedExisting}\n\n${changelogMatch[3]}`;
          finalReadme = updatedReadme.replace(changelogRegex, insertion);
        }
      }
    }
    fs.writeFileSync(distReadmePath, finalReadme);
  }

  copyLanguagesDir();

  return Promise.resolve();
}

function zipTask() {
  const versionLabel = resolveVersionLabel();
  const bundleDir = path.resolve(BUNDLE_DIR);
  fs.mkdirSync(bundleDir, { recursive: true });

  const baseName = process.env.BUNDLE_NAME ? process.env.BUNDLE_NAME.replace(/\.zip$/i, '') : 'Aivec-AI-Search-Schema';
  const archiveName = `${baseName}-${versionLabel}.zip`;
  const folderName = process.env.BUNDLE_DIRNAME || PACKAGE_FOLDER;

  return import('gulp-zip').then(({ default: zip }) =>
    gulp.src(`${DIST}/**/*`, { base: DIST, allowEmpty: true, encoding: false })
      .pipe(prefixDistFolder(folderName))
      .pipe(zip(archiveName))
      .pipe(gulp.dest(bundleDir))
  );
}

function watch() { gulp.watch(paths.scss, gulp.series(scss)); }

const build = gulp.series(clean, gulp.parallel(scss, copy), generateMetadata);

exports.clean = clean;
exports.scss = scss;
exports.copy = copy;
exports.build = build;
exports.watch = watch;
exports.zip = gulp.series(build, zipTask);
exports.default = build;
