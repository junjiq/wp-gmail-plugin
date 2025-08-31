#!/usr/bin/env node

/**
 * WordPress Plugin Build Tool (Node.js Version)
 * WP Gmail Plugin用の高機能ビルドツール
 */

const fs = require('fs-extra');
const path = require('path');
const archiver = require('archiver');
const chalk = require('chalk');
const glob = require('glob');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');

// 設定
const CONFIG = {
    pluginName: 'wp-gmail-plugin',
    pluginMainFile: 'wp-gmail-plugin.php',
    outputDir: 'dist',
    tempDir: 'dist/temp',
    excludePatterns: [
        '*.log',
        '*.tmp',
        '.git*',
        '.vscode/**',
        '.idea/**',
        'node_modules/**',
        'dist/**',
        'tests/**',
        '*.dev.*',
        'build-*.{ps1,bat,js}',
        'package*.json',
        'composer.lock',
        '.env*',
        '*.zip'
    ]
};

// コマンドライン引数の解析
const argv = yargs(hideBin(process.argv))
    .option('version', {
        alias: 'v',
        type: 'string',
        description: 'Plugin version',
        default: '1.0.0'
    })
    .option('output', {
        alias: 'o',
        type: 'string',
        description: 'Output directory',
        default: CONFIG.outputDir
    })
    .option('dev', {
        type: 'boolean',
        description: 'Development build (no optimization)',
        default: false
    })
    .option('prod', {
        type: 'boolean',
        description: 'Production build (with optimization)',
        default: false
    })
    .option('optimize', {
        type: 'boolean',
        description: 'Enable optimization',
        default: false
    })
    .option('validate-only', {
        type: 'boolean',
        description: 'Only validate files without building',
        default: false
    })
    .option('verbose', {
        type: 'boolean',
        description: 'Verbose output',
        default: false
    })
    .help()
    .argv;

// ログ機能
class Logger {
    constructor(verbose = false) {
        this.verbose = verbose;
        this.startTime = Date.now();
    }

    info(message) {
        console.log(chalk.blue('ℹ'), message);
    }

    success(message) {
        console.log(chalk.green('✓'), message);
    }

    warn(message) {
        console.log(chalk.yellow('⚠'), message);
    }

    error(message) {
        console.log(chalk.red('✗'), message);
    }

    verbose(message) {
        if (this.verbose) {
            console.log(chalk.gray('→'), message);
        }
    }

    title(message) {
        console.log('\n' + chalk.cyan.bold('='.repeat(50)));
        console.log(chalk.cyan.bold(message));
        console.log(chalk.cyan.bold('='.repeat(50)));
    }

    getElapsedTime() {
        return ((Date.now() - this.startTime) / 1000).toFixed(2);
    }
}

// メインビルダークラス
class PluginBuilder {
    constructor(options) {
        this.options = options;
        this.logger = new Logger(options.verbose);
        this.pluginDir = path.join(options.output, 'temp', CONFIG.pluginName);
    }

    async build() {
        try {
            this.logger.title(`WordPress Plugin Builder - ${CONFIG.pluginName} v${this.options.version}`);

            // 1. 初期化
            await this.initialize();

            // 2. 検証
            await this.validateFiles();

            if (this.options.validateOnly) {
                this.logger.success('Validation completed successfully');
                return;
            }

            // 3. ファイルコピー
            await this.copyFiles();

            // 4. バージョン更新
            await this.updateVersion();

            // 5. 最適化
            if (this.options.optimize || this.options.prod) {
                await this.optimizeFiles();
            }

            // 6. ZIPパッケージ作成
            const zipPath = await this.createZipPackage();

            // 7. インストール情報生成
            await this.generateInstallInfo();

            // 8. クリーンアップ
            await this.cleanup();

            // 9. 完了報告
            this.logger.title('Build Completed Successfully');
            this.logger.success(`Package: ${path.basename(zipPath)}`);
            this.logger.success(`Location: ${this.options.output}`);
            this.logger.success(`Build time: ${this.logger.getElapsedTime()}s`);
            this.logger.info('Ready for WordPress installation!');

        } catch (error) {
            this.logger.error(`Build failed: ${error.message}`);
            await this.cleanup();
            process.exit(1);
        }
    }

    async initialize() {
        this.logger.info('Initializing build environment...');

        // 出力ディレクトリをクリア
        await fs.remove(this.options.output);
        await fs.ensureDir(this.options.output);
        await fs.ensureDir(path.dirname(this.pluginDir));

        this.logger.verbose(`Output directory: ${this.options.output}`);
        this.logger.verbose(`Plugin directory: ${this.pluginDir}`);
    }

    async validateFiles() {
        this.logger.info('Validating plugin files...');

        const requiredFiles = [
            CONFIG.pluginMainFile,
            'README.md',
            'includes/class-gmail-api-client.php',
            'includes/class-gmail-oauth.php',
            'includes/class-gmail-email-manager.php',
            'includes/functions.php',
            'templates/admin-main.php',
            'templates/admin-settings.php',
            'assets/css/style.css',
            'assets/js/script.js'
        ];

        const missingFiles = [];
        for (const file of requiredFiles) {
            if (!await fs.pathExists(file)) {
                missingFiles.push(file);
            }
        }

        if (missingFiles.length > 0) {
            throw new Error(`Missing required files: ${missingFiles.join(', ')}`);
        }

        this.logger.success('All required files found');
    }

    async copyFiles() {
        this.logger.info('Copying plugin files...');

        // 除外パターンを使用してファイルを取得
        const allFiles = glob.sync('**/*', {
            ignore: CONFIG.excludePatterns,
            dot: false,
            nodir: true
        });

        let copiedCount = 0;
        for (const file of allFiles) {
            const sourcePath = file;
            const destPath = path.join(this.pluginDir, file);

            await fs.ensureDir(path.dirname(destPath));
            await fs.copy(sourcePath, destPath);

            this.logger.verbose(`Copied: ${file}`);
            copiedCount++;
        }

        this.logger.success(`Copied ${copiedCount} files`);
    }

    async updateVersion() {
        this.logger.info(`Updating version to ${this.options.version}...`);

        const mainFilePath = path.join(this.pluginDir, CONFIG.pluginMainFile);
        let content = await fs.readFile(mainFilePath, 'utf8');

        // プラグインヘッダーのバージョンを更新
        content = content.replace(/Version:\s*[\d\.]+/, `Version: ${this.options.version}`);

        // 定数のバージョンを更新
        content = content.replace(
            /define\('WP_GMAIL_PLUGIN_VERSION',\s*'[\d\.]+'\)/,
            `define('WP_GMAIL_PLUGIN_VERSION', '${this.options.version}')`
        );

        await fs.writeFile(mainFilePath, content, 'utf8');
        this.logger.success(`Version updated in ${CONFIG.pluginMainFile}`);
    }

    async optimizeFiles() {
        this.logger.info('Optimizing plugin files...');

        // CSS最適化
        const cssFiles = glob.sync('**/*.css', { cwd: this.pluginDir });
        for (const cssFile of cssFiles) {
            const filePath = path.join(this.pluginDir, cssFile);
            let content = await fs.readFile(filePath, 'utf8');

            // 基本的なCSS最小化
            content = content
                .replace(/\/\*[\s\S]*?\*\//g, '') // コメント削除
                .replace(/\s+/g, ' ') // 空白を1つに
                .replace(/;\s*}/g, '}') // 最後のセミコロン削除
                .trim();

            await fs.writeFile(filePath, content, 'utf8');
            this.logger.verbose(`Optimized CSS: ${cssFile}`);
        }

        // JS最適化（基本的な空白削除のみ）
        const jsFiles = glob.sync('**/*.js', { cwd: this.pluginDir });
        for (const jsFile of jsFiles) {
            const filePath = path.join(this.pluginDir, jsFile);
            let content = await fs.readFile(filePath, 'utf8');

            // 基本的なJS最適化
            content = content
                .replace(/\/\*[\s\S]*?\*\//g, '') // ブロックコメント削除
                .replace(/\/\/.*$/gm, '') // 行コメント削除
                .replace(/\s+/g, ' ') // 空白を1つに
                .trim();

            await fs.writeFile(filePath, content, 'utf8');
            this.logger.verbose(`Optimized JS: ${jsFile}`);
        }

        this.logger.success('File optimization completed');
    }

    async createZipPackage() {
        this.logger.info('Creating ZIP package...');

        const zipFileName = `${CONFIG.pluginName}-v${this.options.version}.zip`;
        const zipPath = path.join(this.options.output, zipFileName);

        return new Promise((resolve, reject) => {
            const output = fs.createWriteStream(zipPath);
            const archive = archiver('zip', {
                zlib: { level: 9 } // 最高圧縮レベル
            });

            output.on('close', () => {
                const sizeInMB = (archive.pointer() / (1024 * 1024)).toFixed(2);
                this.logger.success(`ZIP package created: ${zipFileName} (${sizeInMB} MB)`);
                resolve(zipPath);
            });

            archive.on('error', (err) => {
                reject(err);
            });

            archive.pipe(output);
            archive.directory(this.pluginDir, CONFIG.pluginName);
            archive.finalize();
        });
    }

    async generateInstallInfo() {
        this.logger.info('Generating installation info...');

        const installInfo = {
            plugin_name: 'WP Gmail Plugin',
            version: this.options.version,
            file_name: `${CONFIG.pluginName}-v${this.options.version}.zip`,
            build_date: new Date().toISOString(),
            build_type: this.options.dev ? 'development' : 'production',
            php_version_required: '7.4',
            wordpress_version_required: '5.0',
            installation_steps: [
                '1. Download the ZIP file',
                '2. Go to WordPress Admin → Plugins → Add New',
                '3. Click "Upload Plugin"',
                '4. Choose the ZIP file and click "Install Now"',
                '5. Activate the plugin',
                '6. Go to Gmail → Settings to configure API credentials'
            ],
            configuration_required: {
                google_cloud_console: 'Create OAuth 2.0 credentials',
                gmail_api: 'Enable Gmail API',
                redirect_uri: 'Add redirect URI to Google Cloud Console'
            },
            features: [
                'Gmail API integration',
                'Email sending and receiving',
                'OAuth 2.0 authentication',
                'Admin dashboard',
                'Frontend shortcodes',
                'Responsive design'
            ]
        };

        const infoPath = path.join(this.options.output, 'install-info.json');
        await fs.writeJSON(infoPath, installInfo, { spaces: 2 });

        this.logger.success('Installation info generated: install-info.json');
    }

    async cleanup() {
        this.logger.info('Cleaning up temporary files...');

        const tempDir = path.join(this.options.output, 'temp');
        if (await fs.pathExists(tempDir)) {
            await fs.remove(tempDir);
            this.logger.verbose('Temporary directory removed');
        }
    }
}

// メイン実行
async function main() {
    const builder = new PluginBuilder({
        version: argv.version,
        output: argv.output,
        dev: argv.dev,
        prod: argv.prod,
        optimize: argv.optimize,
        validateOnly: argv['validate-only'],
        verbose: argv.verbose
    });

    await builder.build();
}

// エラーハンドリング
process.on('unhandledRejection', (reason, promise) => {
    console.error(chalk.red('✗'), 'Unhandled Rejection at:', promise, 'reason:', reason);
    process.exit(1);
});

process.on('uncaughtException', (error) => {
    console.error(chalk.red('✗'), 'Uncaught Exception:', error);
    process.exit(1);
});

// 実行
if (require.main === module) {
    main().catch(error => {
        console.error(chalk.red('✗'), 'Build failed:', error.message);
        process.exit(1);
    });
}

module.exports = PluginBuilder;
