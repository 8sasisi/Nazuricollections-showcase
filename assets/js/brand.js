function brandInitials(value) {
  const normalized = String(value || "").trim().replace(/\s+/gu, " ");
  const words = normalized.match(/\p{L}+/gu) || [];
  if (!words.length) return "GF";

  const segments = (text) => {
    if (typeof Intl.Segmenter === "function") {
      return [...new Intl.Segmenter(undefined, { granularity: "grapheme" }).segment(text)]
        .map((part) => part.segment);
    }
    return Array.from(text);
  };
  const letters = words.length > 1
    ? [segments(words[0])[0], segments(words[1])[0]]
    : segments(words[0]).slice(0, 2);
  let initials = letters.map((letter) => letter ? letter.toLocaleUpperCase() : "F").join("");
  initials = segments(initials).slice(0, 2).join("");
  if (!initials) return "GF";
  return segments(initials).length === 1 ? `${initials}F` : initials;
}

function syncBrandLogo(image) {
  const fallback = image.parentElement?.querySelector("[data-brand-initials]");
  if (!fallback) return;
  fallback.hidden = false;
  image.hidden = true;
  if (!image.getAttribute("src")) return;

  image.onload = () => {
    image.hidden = false;
    fallback.hidden = true;
  };
  image.onerror = () => {
    image.hidden = true;
    fallback.hidden = false;
  };
  if (image.complete) {
    if (image.naturalWidth > 0) image.onload();
    else image.onerror();
  }
}

window.brandInitials = brandInitials;
window.syncBrandLogo = syncBrandLogo;

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-brand-image]").forEach(syncBrandLogo);
});
