export function countCodeLines(input: string): number {
  if (!input) return 1;

  const brCount = (input.match(/<br\s*\/?>/gi) ?? []).length;
  const newlineCount = (input.match(/\n/g) ?? []).length;

  return Math.max(brCount + 1, newlineCount + 1);
}

export function buildCodeGutter(lineCount: number): string | undefined {
  return lineCount <= 1 ? undefined : Array.from({ length: lineCount }, (_, i) => i + 1).join("\n");
}
