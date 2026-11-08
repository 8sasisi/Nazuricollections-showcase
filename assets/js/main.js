function textElement(tagName, className, text) {
  const element = document.createElement(tagName);
  if (className) element.className = className;
  element.textContent = text;
  return element;
}

function validSocialUrl(value, host) {
  try {
    const url = new URL(value);
    return url.protocol === "https:" && [host, `www.${host}`].includes(url.hostname.toLowerCase())
      ? url.href
      : null;
  } catch {
    return null;
  }
}

function buildWhatsAppUrl(number, message = "") {
  if (!/^\d{8,15}$/.test(number || "")) return null;
  const url = new URL(`https://wa.me/${number}`);
  if (message) url.searchParams.set("text", message);
  return url.href;
}

function applyBrandIdentity(identity = {}) {
  const name = typeof identity.name === "string" && identity.name.trim()
    ? identity.name.trim()
    : "Grant Fashion";
  const tagline = typeof identity.tagline === "string" ? identity.tagline.trim() : "";
  const description = typeof identity.description === "string" ? identity.description.trim() : "";
  const defaultWordmark = name === "Grant Fashion" && !tagline;
  const displayedName = defaultWordmark ? "Grant" : name;
  const displayedTagline = defaultWordmark ? "Fashion" : tagline;
  const initials = typeof identity.initials === "string" && identity.initials.length === 2
    ? identity.initials
    : window.brandInitials(name);
  const replaceBrand = (value) => String(value).replace(/Grant Fashion/gi, name);

  document.querySelectorAll("[data-brand-name]").forEach((element) => {
    element.textContent = displayedName;
  });
  document.querySelectorAll("[data-brand-tagline]").forEach((element) => {
    element.textContent = displayedTagline;
    element.hidden = !displayedTagline;
  });
  document.querySelectorAll("[data-brand-copy]").forEach((element) => {
    element.textContent = replaceBrand(element.textContent);
  });
  document.querySelectorAll("[data-brand-connect]").forEach((element) => {
    element.textContent = `Connect with ${name}`;
  });
  document.querySelectorAll("[aria-label]").forEach((element) => {
    element.setAttribute("aria-label", replaceBrand(element.getAttribute("aria-label")));
  });

  const logoUrl = typeof identity.logo === "string" ? identity.logo : "";
  let safeLogoUrl = "";
  if (logoUrl) {
    try {
      const url = new URL(logoUrl, window.location.origin);
      if (url.origin === window.location.origin && url.pathname === "/api/media.php") {
        safeLogoUrl = url.href;
      }
    } catch {
      safeLogoUrl = "";
    }
  }
  document.querySelectorAll("[data-brand-logo]").forEach((image) => {
    const mark = image.parentElement?.querySelector("[data-brand-initials]");
    if (mark) mark.textContent = initials;
    image.alt = `${name} logo`;
    if (safeLogoUrl) image.src = safeLogoUrl;
    else image.removeAttribute("src");
    window.syncBrandLogo(image);
  });

  document.querySelectorAll("[data-brand-description]").forEach((element) => {
    element.textContent = description;
    element.hidden = !description;
  });
  document.title = replaceBrand(document.title);
  document.querySelectorAll('meta[name="description"], meta[property="og:description"]').forEach((meta) => {
    meta.content = description || replaceBrand(meta.content);
  });
  document.querySelectorAll('meta[property="og:site_name"]').forEach((meta) => {
    meta.content = name;
  });
  document.querySelectorAll('meta[property="og:title"]').forEach((meta) => {
    meta.content = replaceBrand(meta.content);
  });
}

function updateContactLinks(settings = {}) {
  document.querySelectorAll("[data-setting-link]").forEach((link) => {
    const setting = link.dataset.settingLink;
    let url = null;
    if (setting === "business_whatsapp") {
      url = buildWhatsAppUrl(settings[setting] || "");
    } else if (setting === "business_phone") {
      const phone = String(settings[setting] || "").trim();
      url = /^\+?[0-9][0-9 ()-]{6,38}$/.test(phone) ? `tel:${phone.replace(/[^+0-9]/g, "")}` : null;
    } else if (setting === "instagram_url") {
      url = validSocialUrl(settings[setting] || "", "instagram.com");
    } else if (setting === "tiktok_url") {
      url = validSocialUrl(settings[setting] || "", "tiktok.com");
    }

    if (url) {
      link.href = url;
      if (url.startsWith("https://")) {
        link.target = "_blank";
        link.rel = "noopener noreferrer";
      } else {
        link.removeAttribute("target");
        link.removeAttribute("rel");
      }
      link.hidden = false;
    } else {
      link.hidden = true;
    }
    const listItem = link.closest("li");
    if (listItem) listItem.hidden = link.hidden;
  });

  document.querySelectorAll("[data-setting-text]").forEach((element) => {
    const value = settings[element.dataset.settingText] || "";
    if (value) {
      element.textContent = value;
      element.hidden = false;
    } else {
      element.hidden = true;
    }
  });

  document.querySelectorAll(".social-links").forEach((container) => {
    container.hidden = !container.querySelector("a:not([hidden])");
  });
}

function updateEditableCopy(content) {
  document.querySelectorAll("[data-content]").forEach((element) => {
    const [group, key] = element.dataset.content.split(".");
    const section = content.sections?.[group];
    const value = section?.[key];
    if (typeof value !== "string") return;
    if (element.matches("a")) {
      element.textContent = value;
      const target = section?.button_url;
      if (typeof target === "string" && /^(?:pages\/[a-z0-9-]+\.html|#[a-z0-9_-]+)$/i.test(target)) {
        const depth = window.location.pathname.includes("/pages/") ? "../" : "";
        element.href = `${depth}${target}`;
      }
    } else {
      element.textContent = value;
    }
  });

  document.querySelectorAll("[data-page-content]").forEach((element) => {
    const page = content.pages?.[element.dataset.pageContent];
    if (!page) return;
    const title = document.querySelector(`[data-page-title="${element.dataset.pageContent}"]`);
    if (title) title.textContent = page.title;
    element.textContent = page.content;
  });

  document.querySelectorAll("[data-media-section]").forEach((image) => {
    const section = content.sections?.[image.dataset.mediaSection];
    if (section?.image) {
      image.src = section.image;
      image.alt = section.image_alt || "";
      image.hidden = false;
    }
  });
  document.querySelectorAll("[data-media-collection]").forEach((image) => {
    const collection = content.collections?.[image.dataset.mediaCollection];
    if (collection?.image) {
      image.src = collection.image;
      image.alt = collection.image_alt || "";
      image.hidden = false;
    }
    if (collection?.name) {
      const label = image.parentElement?.querySelector("span");
      if (label) label.textContent = collection.name;
    }
  });
  document.querySelectorAll("[data-collection-description]").forEach((element) => {
    const collection = content.collections?.[element.dataset.collectionDescription];
    if (collection?.description) element.textContent = collection.description;
    const heading = element.closest(".page-hero-inner")?.querySelector("h1");
    if (collection?.name && heading) heading.textContent = collection.name;
  });
}

function renderProductCard(product) {
  const column = document.createElement("div");
  column.className = "col-sm-6 col-lg-3";
  const card = document.createElement("article");
  card.className = "product-card";
  const image = product.image ? document.createElement("img") : document.createElement("div");
  image.className = "product-image";
  if (product.image) {
    image.src = product.image;
    image.alt = product.name ? `${product.name} product photograph` : "Product photograph";
    image.loading = "lazy";
  } else {
    image.classList.add("product-image-missing");
    image.textContent = "Photograph not supplied";
    image.setAttribute("role", "img");
    image.setAttribute("aria-label", "Product photograph not supplied");
  }
  const body = document.createElement("div");
  body.className = "product-body";
  body.append(textElement("p", "category", product.category || ""));
  body.append(textElement("h3", "", product.name || ""));
  if (Array.isArray(product.colors) && product.colors.length) {
    body.append(textElement("p", "meta", product.colors.join(" · ")));
  }
  if (product.description) body.append(textElement("p", "", product.description));
  if (product.price !== null && product.price !== undefined) {
    const price = new Intl.NumberFormat(undefined, {
      style: product.currency ? "currency" : "decimal",
      currency: product.currency || undefined
    }).format(product.price);
    body.append(textElement("p", "meta", price));
  }
  const inquiry = document.createElement("a");
  inquiry.className = "text-link";
  inquiry.href = "../pages/contact.html";
  inquiry.textContent = "Inquire now";
  body.append(inquiry);
  card.append(image, body);
  column.append(card);
  return column;
}

function renderProducts(container, products) {
  if (!container) return;
  container.replaceChildren();
  if (!products.length) {
    const empty = textElement("p", "empty-state", "No published products are available yet.");
    empty.setAttribute("role", "status");
    container.append(empty);
    return;
  }
  products.forEach((product) => container.append(renderProductCard(product)));
}

async function loadSiteContent() {
  try {
    const response = await fetch("/api/site-content.php", { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error("Site content request failed");
    const content = await response.json();
    updateEditableCopy(content);
    applyBrandIdentity(content.brand);
    updateContactLinks(content.settings || {});
    return {
      settings: content.settings || {},
      brandName: content.brand?.name || "Grant Fashion"
    };
  } catch {
    document.querySelectorAll("[data-content-status]").forEach((element) => {
      element.textContent = "Some website information is temporarily unavailable.";
    });
    updateContactLinks();
    return { settings: {}, brandName: "Grant Fashion" };
  }
}

async function loadProducts() {
  const targets = [
    ["product-grid-abayas", "abayas"],
    ["product-grid-magauni", "magauni"],
    ["product-grid-home", null]
  ];
  await Promise.all(targets.map(async ([id, collection]) => {
    const container = document.getElementById(id);
    if (!container) return;
    try {
      const url = new URL("/api/products.php", window.location.origin);
      if (collection) url.searchParams.set("collection", collection);
      const response = await fetch(url, { headers: { Accept: "application/json" } });
      if (!response.ok) throw new Error("Product request failed");
      const data = await response.json();
      const products = Array.isArray(data.products) ? data.products : [];
      const featured = products
        .filter((product) => product.collection === "abayas").slice(0, 2)
        .concat(products.filter((product) => product.collection === "magauni").slice(0, 2));
      renderProducts(container, id === "product-grid-home" ? featured : products);
    } catch {
      const state = textElement("p", "empty-state", "Product information is temporarily unavailable.");
      state.setAttribute("role", "status");
      container.replaceChildren(state);
    }
  }));
}

function initContactForm(settings, brandName = "Grant Fashion") {
  const form = document.querySelector(".contact-form");
  if (!form) return;
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const number = settings.business_whatsapp || "";
    const target = buildWhatsAppUrl(number);
    const feedback = form.querySelector("[data-contact-feedback]");
    if (!target) {
      if (feedback) {
        feedback.textContent = "WhatsApp contact has not been configured yet. Please check back later.";
        feedback.hidden = false;
      }
      return;
    }
    const formData = new FormData(form);
    const firstName = String(formData.get("firstName") || "").trim();
    const lastName = String(formData.get("lastName") || "").trim();
    const email = String(formData.get("email") || "").trim();
    const message = String(formData.get("message") || "").trim();
    if (!firstName || !message) {
      form.reportValidity();
      return;
    }
    const fullName = [firstName, lastName].filter(Boolean).join(" ");
    const whatsappMessage = `Hello ${brandName}, my name is ${fullName}.${email ? ` Email: ${email}` : ""}\n\n${message}`;
    window.open(buildWhatsAppUrl(number, whatsappMessage), "_blank", "noopener,noreferrer");
  });
}

document.addEventListener("DOMContentLoaded", async () => {
  initMobileMenuFallback();
  const [site] = await Promise.all([loadSiteContent(), loadProducts()]);
  initContactForm(site.settings, site.brandName);
});

function initMobileMenuFallback() {
  const menu = document.getElementById("mobileMenu");
  const toggler = document.querySelector("[data-menu-toggle]");
  if (!menu || !toggler) return;

  const closeMenu = () => {
    menu.classList.remove("show");
    document.body.classList.remove("menu-open");
    toggler.setAttribute("aria-expanded", "false");
    toggler.focus();
  };
  toggler.addEventListener("click", () => {
    const isOpen = menu.classList.toggle("show");
    document.body.classList.toggle("menu-open", isOpen);
    toggler.setAttribute("aria-expanded", String(isOpen));
    if (isOpen) menu.querySelector('[data-bs-dismiss="offcanvas"]')?.focus();
  });
  menu.querySelector("[data-menu-close]")?.addEventListener("click", closeMenu);
  menu.querySelectorAll("a").forEach((link) => link.addEventListener("click", closeMenu));
  document.addEventListener("click", (event) => {
    if (document.body.classList.contains("menu-open") && !menu.contains(event.target) && !toggler.contains(event.target)) {
      closeMenu();
    }
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeMenu();
  });
}
