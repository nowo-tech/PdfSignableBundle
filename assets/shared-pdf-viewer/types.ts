/** Minimal PDF doc interface for scale computation (getPage, getViewport). */
export interface IPdfDocForScale {
  getPage(n: number): Promise<{
    getViewport(o: { scale: number }): { width: number; height: number; scale: number };
  }>;
}
