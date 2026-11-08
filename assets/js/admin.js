document.addEventListener("submit", (event) => {
  const prompt = event.target.dataset.confirm;
  if (prompt && !window.confirm(prompt)) {
    event.preventDefault();
  }
});

document.addEventListener("input", (event) => {
  const field = event.target;
  const previewKey = field.dataset.brandPreviewInput;
  if (!previewKey) return;
  if (previewKey === "name" || previewKey === "tagline") {
    updateBrandPreview();
    return;
  }
  const preview = document.querySelector(`[data-brand-preview-${previewKey}]`);
  if (preview) preview.textContent = field.value;
});

document.addEventListener("change", (event) => {
  const field = event.target;
  const preview = document.querySelector("[data-brand-preview-logo]");
  if (!preview) return;
  if (field.name === "remove_brand_logo") {
    if (preview.dataset.objectUrl) {
      URL.revokeObjectURL(preview.dataset.objectUrl);
      delete preview.dataset.objectUrl;
    }
    if (field.checked) preview.removeAttribute("src");
    else if (preview.dataset.savedSrc) preview.src = preview.dataset.savedSrc;
    else preview.removeAttribute("src");
    window.syncBrandLogo(preview);
    return;
  }
  if (field.name !== "brand_logo" || !field.files?.[0]) return;
  if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
  const objectUrl = URL.createObjectURL(field.files[0]);
  preview.dataset.objectUrl = objectUrl;
  preview.src = objectUrl;
  window.syncBrandLogo(preview);
});

function updateBrandPreview() {
  const name = document.querySelector('[data-brand-preview-input="name"]')?.value.trim() || "";
  const tagline = document.querySelector('[data-brand-preview-input="tagline"]')?.value.trim() || "";
  const defaultWordmark = name === "Grant Fashion" && !tagline;
  const namePreview = document.querySelector("[data-brand-preview-name]");
  const taglinePreview = document.querySelector("[data-brand-preview-tagline]");
  const initialsPreview = document.querySelector(".brand-preview [data-brand-initials]");
  if (initialsPreview && window.brandInitials) initialsPreview.textContent = window.brandInitials(name);
  if (namePreview) namePreview.textContent = defaultWordmark ? "Grant" : name;
  if (taglinePreview) {
    taglinePreview.textContent = defaultWordmark ? "Fashion" : tagline;
    taglinePreview.hidden = !taglinePreview.textContent;
  }
}

document.addEventListener("DOMContentLoaded", updateBrandPreview);
