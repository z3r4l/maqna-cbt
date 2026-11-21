import type { KatexOptions } from 'katex';

export const safeKatexOptions: KatexOptions = {
  throwOnError: true,
  trust: false,
  strict: 'error',
  maxExpand: 100,
  maxSize: 10,
};
