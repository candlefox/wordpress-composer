const { src, dest, watch, series, parallel } = require('gulp');
const sass = require('gulp-sass');
const autoprefixer = require('gulp-autoprefixer');
const concat = require('gulp-concat');
const uglify = require('gulp-uglify');
const browserSync = require('browser-sync').create();

const themeDirectory = 'wp-content/themes/training-genesis/';
const paths = {
	scss: themeDirectory + 'sass/**/*.scss',
	allJs: themeDirectory + 'js/src/**/*.js',
	singleJs: themeDirectory + 'js/src/*.js',
	mainJs: themeDirectory + 'js/src/main/*.js',
	jsOutput: themeDirectory + 'js'
};

const sassOptions = {
	errLogToConsole: true,
	outputStyle: 'compressed'
};

function handleError(error) {
	console.log(error.toString());
	this.emit('end');
}

function styles(cb) {
	src(paths.scss)
		.pipe(sass(sassOptions).on('error', sass.logError))
		.pipe(autoprefixer())
		.pipe(dest(themeDirectory))
		.pipe(browserSync.stream());

	cb();
}

function scripts(cb) {
	src(paths.mainJs)
		.pipe(concat('main.js'))
		.pipe(uglify().on('error', handleError))
		.pipe(dest(paths.jsOutput));

	src(paths.singleJs)
		.pipe(uglify().on('error', handleError))
		.pipe(dest(paths.jsOutput));

	cb();
}

function watchAssets() {
	browserSync.init({
		proxy: 'composer.training.com.au'
	});
	watch(paths.scss, styles);
	watch(paths.allJs, scripts);
};

exports.styles = styles;
exports.scripts = scripts;
exports.default = series(parallel(styles, scripts), watchAssets);