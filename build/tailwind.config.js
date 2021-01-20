const mapKeys = require('lodash/mapKeys');
const mapValues = require('lodash/mapValues');
const range = require('lodash/range');
const variables = require('../resources/assets/variables.json');

// converters and calculators
const relative = (px, unit = 'rem', base = variables['browser-default-font-size']) => `${px / base}${unit}`;
const letterSpacing = value => `${value / 1000}em`;
const ratio = (x, y) => `${y / x * 100}%`;

// values
const easing = mapValues(variables.easing, val => `cubic-bezier(${val[0]}, ${val[1]}, ${val[2]}, ${val[3]})`);

const screens = mapValues(variables.breakpoints, px => relative(px, 'em'));

const c = variables.columns;
const widths = mapKeys(mapValues(range(0, c), (v) => ratio(c, v + 1)), (v, k) => `${parseInt(k, 10) + 1}/${c}`);

// tailwind settings
module.exports = {
	purge: false,
	target: 'relaxed',
	theme: {
		screens,
		colors: {
			transparent: 'transparent',
			current: 'currentColor',
			inherit: 'inherit',
			brand: {
				red: '#ff585d'
			},
			black: '#000',
			white: '#fff',
			grey: {
				100: '#fafafa',
				200: '#eee',
				300: '#ddd',
				400: '#ddd',
				500: '#aaa',
				600: '#888',
				700: '#444',
				800: '#222',
				900: '#111',
			},
			blue: '#00f',
			green: '#24b35d',
			red: '#f50023',
			social: {
				twitter: '#55acee',
				facebook: '#3b5998',
				youtube: '#bb0000',
				pinterest: '#cb2027',
				linkedin: '#007bb5',
				instagram: '#8a3ab9',
			},
		},
		fontFamily: {
			body: ['custom-body', 'Helvetica', 'sans-serif'],
			heading: ['custom-heading', 'Georgia', 'serif'],
			system: ['system-ui', 'sans-serif'],
		},
		fontSize: {
			xs: relative(12),
			sm: relative(14),
			base: relative(16),
			lg: relative(18),
			xl: relative(20),
			'2xl': relative(22),
			'3xl': relative(26),
			'4xl': relative(30),
			'5xl': relative(36),
			'6xl': relative(44),
			full: '100%',
		},
		fontWeight: {
			normal: 400,
			bold: 700,
		},
		letterSpacing: {
			normal: 0,
			wide: letterSpacing(50),
		},
		lineHeight: {
			none: 1,
			tight: 1.1,
			snug: 1.2,
			normal: 1.5,
			relaxed: 1.75,
			loose: 2,
		},
		transitionTimingFunction: easing,
		extend: {
			boxShadow: theme => ({
				focus: `0 0 5px ${theme('colors.blue')}`
			}),
			inset: (theme, { negative }) => ({
				'1/2': '50%',
				...widths,
				...(negative(widths)),
			}),
			maxWidth: {
				container: relative(1400),
				copy: '35em',
			},
			padding: {
				full: '100%',
				logo: ratio(300, 87),
				'9/16': ratio(16, 9),
				'3/4': ratio(4, 3),
				'4/3': ratio(3, 4),
			},
			spacing: {
				em: '1em',
				'1/2em': '.5em',
			},
			width: {
				...widths,
			},
			zIndex: {
				'-1': -1,
				1: 1,
			},
		},
	},
	variants: {},
	corePlugins: {
		container: false,
	},
};
