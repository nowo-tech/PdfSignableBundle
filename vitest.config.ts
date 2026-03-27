import { defineConfig } from 'vitest/config';

/** Vitest config for unit testing TS modules. Runs alongside PHPUnit. */
export default defineConfig({
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['src/Resources/assets/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reportsDirectory: 'coverage-ts',
      include: ['src/Resources/assets/**/*.ts'],
      exclude: [
        'src/Resources/assets/**/*.test.ts',
        'src/Resources/assets/**/*.d.ts',
        'src/Resources/assets/signable-editor.ts',
        'src/Resources/assets/acroform-editor.ts',
      ],
    },
  },
});
