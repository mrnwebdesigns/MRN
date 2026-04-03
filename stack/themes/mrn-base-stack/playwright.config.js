const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests/playwright',
	timeout: 30 * 1000,
	expect: {
		timeout: 5 * 1000,
	},
	fullyParallel: true,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: process.env.CI ? [['html', { open: 'never' }], ['list']] : 'list',
	use: {
		baseURL: process.env.MRN_PLAYWRIGHT_BASE_URL || 'http://mrn-plugin-stack.local',
		trace: 'retain-on-failure',
		video: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
			},
		},
	],
});
