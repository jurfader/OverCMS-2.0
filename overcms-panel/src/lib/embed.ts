/**
 * Zarządza trybem embed (ukrywanie WP chrome w iframe).
 * Używa jednocześnie URL param (?overcms_embed=1) i ciasteczka —
 * URL param działa zawsze, ciasteczko przeżywa wewnętrzne redirecty JS.
 */
export function buildEmbedUrl(url: string): string {
  document.cookie = 'overcms_embed=1; path=/; SameSite=Lax';
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}overcms_embed=1`;
}

export function clearEmbedCookie(): void {
  document.cookie = 'overcms_embed=; path=/; max-age=0; SameSite=Lax';
}
