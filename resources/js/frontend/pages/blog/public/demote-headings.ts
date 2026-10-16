/**
 * A blog single page renders the post title as the document's one and only
 * `<h1>`. The rich-text editor can still emit `<h1>` inside the body (legacy
 * content, or before the editor stopped offering it), which would give the page
 * a second `<h1>` and break the heading hierarchy search engines expect.
 *
 * Demote every body `<h1>` to `<h2>` so the page keeps a single `<h1>` while
 * preserving the author's heading structure below it.
 */
export function demoteBodyHeadings(html: string): string {
  return html.replace(/<(\/?)h1(\s|>)/gi, "<$1h2$2");
}
