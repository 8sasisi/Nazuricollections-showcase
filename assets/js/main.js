const SITE_CONFIG = {
  whatsappNumber: "REPLACE_WITH_NAZURI_WHATSAPP_NUMBER",
  instagram: "https://www.instagram.com/nazuri_collections?rpxt=MTAxbjE5YnJ2ejRicw==",
  tiktok: "https://www.tiktok.com",
  products: {
    abayas: [
      {
        id: "abaya-eden",
        name: "Eden Abaya",
        category: "Abaya",
        colors: ["Sand", "Cream", "Charcoal"],
        description: "A minimalist silhouette with soft structure and graceful drape.",
        image: "https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=900&q=80"
      },
      {
        id: "abaya-sophia",
        name: "Sophia Abaya",
        category: "Abaya",
        colors: ["Ivory", "Taupe", "Black"],
        description: "Minimal layering and elegant movement designed for everyday refinement.",
        image: "https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=900&q=80"
      },
      {
        id: "abaya-noor",
        name: "Noor Abaya",
        category: "Abaya",
        colors: ["Stone", "Mocha", "Ink"],
        description: "A clean-lined look that balances simplicity with a premium finish.",
        image: "https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80"
      },
      {
        id: "abaya-aisha",
        name: "Aisha Abaya",
        category: "Abaya",
        colors: ["Cream", "Soft Grey", "Espresso"],
        description: "Soft tailoring with understated elegance for polished everyday wear.",
        image: "https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=900&q=80"
      }
    ],
    magauni: [
      {
        id: "gown-muse",
        name: "Ivory Muse",
        category: "Gown",
        colors: ["Ivory", "Champagne", "Pearl"],
        description: "Fluid movement and refined elegance designed for occasion dressing.",
        image: "https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=900&q=80"
      },
      {
        id: "gown-mazie",
        name: "Mazie Gown",
        category: "Magauni",
        colors: ["Blush", "Silver Mist", "Graphite"],
        description: "A graceful statement shape with a softly sculpted silhouette.",
        image: "https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80"
      },
      {
        id: "gown-laila",
        name: "Laila Gown",
        category: "Gown",
        colors: ["Forest", "Plum", "Black"],
        description: "Elegant drape and rich texture for elevated evening styling.",
        image: "https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=900&q=80"
      },
      {
        id: "gown-zuri",
        name: "Zuri Gown",
        category: "Magauni",
        colors: ["Nude", "Warm Beige", "Onyx"],
        description: "Modern structure with a refined finish that transitions beautifully.",
        image: "https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=900&q=80"
      }
    ]
  }
};

function renderProductCards(containerId, products) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = products
    .map(
      (product) => `
        <div class="col-sm-6 col-lg-3">
          <article class="product-card">
            <div class="product-image" style="background-image: linear-gradient(180deg, rgba(31,35,40,0.14), rgba(31,35,40,0.32)), url('${product.image}')"></div>
            <div class="product-body">
              <p class="category">${product.category}</p>
              <h3>${product.name}</h3>
              <p class="meta">${product.colors.join(" • ")}</p>
              <p>${product.description}</p>
              <a class="text-link" href="contact.html">Inquire now <i class="bi bi-arrow-right"></i></a>
            </div>
          </article>
        </div>
      `
    )
    .join("");
}

function initCollectionPage() {
  const abayaGrid = document.getElementById("product-grid-abayas");
  const magauniGrid = document.getElementById("product-grid-magauni");

  if (abayaGrid) renderProductCards("product-grid-abayas", SITE_CONFIG.products.abayas);
  if (magauniGrid) renderProductCards("product-grid-magauni", SITE_CONFIG.products.magauni);
}

document.addEventListener("DOMContentLoaded", () => {
  initCollectionPage();

  const form = document.querySelector(".contact-form");
  if (!form) return;

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const firstName = formData.get("firstName")?.toString().trim() || "Customer";
    const lastName = formData.get("lastName")?.toString().trim() || "";
    const email = formData.get("email")?.toString().trim() || "";
    const message = formData.get("message")?.toString().trim() || "";

    const fullName = [firstName, lastName].filter(Boolean).join(" ");
    const whatsappMessage = encodeURIComponent(
      `Hello Nazuri Collections, my name is ${fullName}.\nEmail: ${email}\n\n${message}`
    );

    window.open(`https://wa.me/${SITE_CONFIG.whatsappNumber}?text=${whatsappMessage}`, "_blank", "noopener,noreferrer");
  });
});
