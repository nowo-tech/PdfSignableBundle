import { defineConfig } from 'vitest/config';

/** Vitest config for unit testing TS modules. Runs alongside PHPUnit. */
export default defineConfig({
  test: {
    globals: true,
    environment: 'node',
    include: ['assets/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      include: ['assets/**/*.ts'],
      exclude: [
        'assets/**/*.test.ts',
        'assets/**/*.d.ts',
        'assets/signable-editor.ts',
        'assets/acroform-editor.ts',
      ],
    },
  },
});
