import typescript from '@rollup/plugin-typescript';

// Output goes to build/ (NOT the live burglebros.js) while the TS port is in
// progress. The deployed game still runs the hand-written burglebros.js; when
// the port is complete and verified, point this at the live file to cut over.
export default {
  input: 'src/ts/Game.ts',
  output: {
    file: 'build/burglebros.js',
    format: 'es',
    sourcemap: false,
    inlineDynamicImports: true,
  },
  plugins: [
    typescript({
      tsconfig: './tsconfig.json',
      outDir: 'build',
    }),
  ],
  treeshake: false,
};
