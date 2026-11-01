# MASTER IMPLEMENTATION PROMPT — NAZURI COLLECTIONS WEBSITE

## 1. YOUR ROLE

Act as a senior frontend developer, UI/UX designer, software architect, and quality-assurance engineer.

Your task is to professionally design and implement the **Nazuri Collections** website from scratch inside the currently opened Visual Studio Code project.

You have permission to inspect, create, edit, and organize project files as necessary to complete the website.

Do not merely provide instructions or sample code. Work directly on the existing project files.

Prioritize accuracy, maintainability, responsive design, performance, accessibility, and a premium fashion-brand experience.

---

## 2. PROJECT LOCATION AND CURRENT STATE

The project is located at:

`D:\projects\showcase`

The current repository contains:

- `.git/` — existing Git repository.
- `Documents/` — contains two project documentation files.
- `README.md` — existing project README.

The project is already connected to its GitHub repository:

`https://github.com/8sasisi/nazuricollections-showcase`

The current working branch is expected to be `main`. Verify this instead of assuming it.

IMPORTANT: This is a fresh implementation. Do not reuse unrelated code or assets from other projects.

---

## 3. MANDATORY FIRST ACTION: READ PROJECT DOCUMENTATION

Before writing or modifying any code:

1. Inspect the `Documents/` directory recursively.
2. Identify the two existing project documentation files.
3. Read both documents completely.
4. Extract and understand all requirements, design decisions, technical constraints, development standards, and project objectives.
5. Treat these two documents as the primary project specifications.
6. Use their requirements throughout implementation.

Do not ignore, replace, or casually reinterpret the documentation.

If a requirement in one document appears to conflict with another, identify the conflict and explain the resolution before proceeding.

If either document is missing, unreadable, or incomplete, stop and report the problem. Do not invent its contents.

After reading both documents, provide a short initial assessment covering:

- Project requirements.
- Technical architecture.
- Required pages and features.
- Proposed directory structure.
- Important constraints.
- Implementation sequence.

Then proceed with implementation. Do not wait for approval for routine decisions already covered by the documentation.

---

## 4. PROJECT IDENTITY

**Brand:** Nazuri Collections

**Business:** Women's fashion and modest fashion boutique.

**Main product categories:**
- Abayas.
- Magauni / Gowns / Dresses.
- Two-piece outfits where applicable.

**Brand tagline:**

"Premium abayas and gowns in town"

**Brand establishment:**

"Since 2023"

**Design direction:**

Create an elegant, modern, premium fashion website inspired by the design principles of luxury fashion brands such as Zara and COS.

Use these brands only as design inspiration. Do not copy their website layouts, branding, assets, or proprietary content.

The website should communicate:

- Elegance.
- Quality.
- Confidence.
- Simplicity.
- Premium fashion.
- Trustworthiness.

---

## 5. CONFIRMED HOMEPAGE DESIGN DECISIONS

These decisions have already been approved. Do not ask the user to choose them again.

### A. Hero: EDITORIAL

Create a premium editorial fashion hero.

Requirements:

- Strong fashion photography.
- Clear visual hierarchy.
- Elegant typography.
- Carefully positioned brand messaging.
- A clear call-to-action.
- Balanced spacing and composition.
- Responsive behavior on mobile and desktop.

The hero should resemble a premium fashion editorial rather than a conventional advertising banner.

Suggested headline:

"Elegance in Every Detail."

Suggested supporting text:

"Discover timeless styles designed to make every moment elegant."

Suggested CTA:

"Explore Collection"

These are proposed marketing copy and can be refined to fit the brand documentation.

### B. Navigation: MINIMAL

Create a clean, minimal navigation system.

Requirements:

- Prominent brand identity/logo.
- Small number of navigation links.
- Elegant hover and active states.
- Minimal visual clutter.
- Responsive mobile navigation.
- Accessible menu controls.
- Proper keyboard navigation.

Use an offcanvas mobile menu if appropriate.

### C. Featured Products: BOTH

The homepage must showcase both:

- Abayas.
- Magauni / Gowns.

Do not make one category appear to be the entire business.

Use a balanced and visually consistent product presentation.

---

## 6. APPROVED BRAND COLOURS

Use this colour palette consistently:

| Name | Hex |
|---|---|
| Charcoal | #1F2328 |
| Off-white | #F5F5F5 |
| Grey | #B5B5B5 |
| Champagne Gold | #C9A66B |
| White | #FFFFFF |

Design rules:

- Use charcoal for strong text and selected backgrounds.
- Use off-white and white for clean surfaces.
- Use grey for secondary information.
- Use champagne gold sparingly for accents, highlights, and selected interactive states.
- Maintain sufficient contrast.
- Avoid excessive gradients, shadows, borders, and decorative effects.

The overall visual experience should feel refined, spacious, and premium.

---

## 7. MANDATORY TECHNOLOGY STACK

Use:

- HTML5.
- CSS3.
- Bootstrap 5.
- Vanilla JavaScript.
- Bootstrap Icons where appropriate.

Do NOT introduce:

- React.
- Next.js.
- Vue.
- Angular.
- Svelte.
- Laravel.
- Django.
- Node.js backend.
- PHP backend.
- Database systems.
- Unnecessary build tools or complex frameworks.

The website is a lightweight frontend showcase.

Use semantic HTML and reusable CSS/JavaScript patterns.

Avoid unnecessary dependencies.

If Bootstrap or Bootstrap Icons are loaded through a CDN, implement a sensible fallback strategy and document the dependency. Consider the project's performance and users with slower internet connections.

---

## 8. FUNCTIONAL SCOPE

This website is a product showcase, not a full e-commerce platform.

Implement the following:

- Homepage.
- Abayas collection page.
- Magauni/Gowns collection page.
- Product detail presentation where appropriate.
- About Us page.
- Contact page.
- Responsive navigation.
- Product browsing.
- Clear calls-to-action.
- WhatsApp contact integration using a placeholder.
- Instagram and TikTok links using placeholders until official URLs are supplied.

Do NOT implement:

- Shopping cart.
- Checkout.
- Online payments.
- Customer accounts.
- Authentication.
- Database.
- Inventory management.
- Order management.
- Admin dashboard.
- Backend APIs.

Do not add features outside the agreed scope unless they are necessary for the showcase experience.

---

## 9. PRODUCT CONTENT AND IMAGE ACCURACY

Product photography is extremely important to this brand.

Follow these rules strictly:

1. Do not download random fashion product images from the internet.
2. Do not use AI-generated fashion images as actual product listings.
3. Do not invent product photographs.
4. Do not invent product names, prices, sizes, colours, availability, or product descriptions.
5. Do not alter the actual appearance of a garment when presenting supplied product images.
6. Do not create misleading product information.

First inspect the project for existing image assets.

Create a sensible asset structure for future product images.

If actual product photographs are not yet available, implement a polished placeholder system that clearly identifies where real product photographs should be added.

Do not make placeholder content appear to be genuine products.

Product cards should be designed to accommodate:

- Product image.
- Product name.
- Category.
- Price, only when supplied.
- Short description, only when supplied.
- Product details/contact action.

Maintain consistent image ratios and suitable object positioning without distorting the photographs.

---

## 10. WHATSAPP AND SOCIAL CONTACT

WhatsApp must remain a placeholder until the user supplies the official business number.

Use this exact placeholder:

`REPLACE_WITH_NAZURI_WHATSAPP_NUMBER`

Do not invent a phone number.

Implement a reusable WhatsApp contact mechanism so the official number can be updated in one place.

If a WhatsApp link is generated, ensure its placeholder status is clear and prevent it from being mistaken for a working business contact.

Instagram and TikTok URLs must also remain clearly identified placeholders until the official accounts are supplied.

Do not invent social media handles or URLs.

---

## 11. PROPOSED HOMEPAGE STRUCTURE

Implement the homepage in this order:

1. Announcement bar, if useful.
2. Minimal navigation.
3. Editorial hero.
4. Shop by Collection.
5. Featured Products.
6. Brand Story / About Nazuri.
7. Social Discovery section.
8. Footer.

### Announcement bar

Keep it subtle and elegant.

Do not invent discounts, delivery promises, promotions, or shipping policies.

### Shop by Collection

Present both:

- Abayas.
- Magauni / Gowns.

Use attractive collection imagery only when real, approved assets are available.

### Featured Products

Showcase both categories.

Keep the layout balanced and responsive.

### Brand Story

Use the confirmed brand information:

"Premium abayas and gowns in town"

"Since 2023"

Do not invent the founder's identity, company history, achievements, or business location details.

### Footer

Include:

- Brand identity.
- Navigation links.
- Contact section.
- WhatsApp placeholder.
- Instagram placeholder.
- TikTok placeholder.
- Copyright information.

---

## 12. RESPONSIVE DESIGN

Use a mobile-first approach.

Test and optimize for these viewport widths:

- 320px.
- 375px.
- 425px.
- 768px.
- 1024px.
- 1440px.

Requirements:

- No horizontal overflow.
- Navigation must work on mobile.
- Product grids must adapt naturally.
- Images must scale correctly.
- Text must remain readable.
- Buttons must be usable on touchscreens.
- Spacing must remain consistent.
- Desktop layouts must use available space effectively.

Do not simply shrink the desktop design for mobile. Design appropriate mobile layouts.

---

## 13. ACCESSIBILITY AND SEO

Implement:

- Semantic HTML5 elements.
- Appropriate heading hierarchy.
- Descriptive image alt attributes.
- Accessible navigation.
- Keyboard-accessible interactive elements.
- Visible focus states.
- Proper form labels where forms exist.
- Sufficient colour contrast.
- Meaningful page titles.
- Meta descriptions.
- Appropriate Open Graph metadata.
- Descriptive URLs or page filenames.

Do not add a contact form that pretends to submit information if no backend or working submission mechanism exists.

---

## 14. PERFORMANCE AND CODE QUALITY

Prioritize:

- Lightweight files.
- Efficient CSS.
- Clean JavaScript.
- Optimized image loading.
- Lazy loading for appropriate below-the-fold images.
- Responsive image handling.
- Avoiding unnecessary JavaScript.
- Reusable components and functions.
- Consistent naming conventions.
- Clear file organization.

Do not place all CSS or JavaScript into one enormous file if a more maintainable structure is appropriate.

Avoid duplicated styles and unnecessary inline CSS.

Use comments where they genuinely improve maintainability.

Ensure there are no obvious browser-console errors.

---

## 15. SECURITY AND PRIVACY

Although this is a static frontend project:

- Avoid exposing secrets or private credentials.
- Do not commit API keys or access tokens.
- Avoid unsafe DOM manipulation.
- Do not use `eval()`.
- Validate and safely handle user-controlled input.
- Avoid unnecessary third-party scripts.
- Do not introduce analytics or tracking without approval.

---

## 16. PROJECT DIRECTORY ORGANIZATION

First inspect the existing project and documentation.

Then create a clean, maintainable structure appropriate for this static website.

A possible structure is:

showcase/
│
├── Documents/
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   │   ├── logo/
│   │   ├── hero/
│   │   ├── abayas/
│   │   ├── gowns/
│   │   └── general/
│   └── icons/
│
├── pages/
│
├── index.html
├── README.md
├── .gitignore
└── ...

You may improve this structure if there is a clear technical reason.

Do not move, rename, or delete the two documentation files.

Do not overwrite existing files without inspecting their contents first.

---

## 17. IMPLEMENTATION WORKFLOW

Work in the following phases.

### PHASE 1 — Project inspection

- Inspect the repository.
- Read both documentation files.
- Check Git status and current branch.
- Inspect existing files and assets.
- Identify missing information.
- Report the proposed implementation structure.

### PHASE 2 — Project foundation

- Establish the directory structure.
- Create the base HTML pages.
- Set up the CSS architecture.
- Set up the JavaScript architecture.
- Configure Bootstrap 5.
- Establish shared typography, colours, spacing, and layout rules.
- Update README.md with accurate project information.
- Add an appropriate .gitignore.

### PHASE 3 — Homepage implementation

Implement the approved homepage:

- Editorial hero.
- Minimal navigation.
- Collection sections.
- Abayas and Gowns featured products.
- Brand story.
- Social discovery.
- Footer.

Ensure that the design is coherent and visually polished.

### PHASE 4 — Remaining pages

Implement the collection, product presentation, About, and Contact pages.

Reuse shared design elements instead of duplicating code.

### PHASE 5 — JavaScript functionality

Implement and test:

- Mobile navigation.
- Offcanvas interactions.
- Collection navigation.
- Product browsing interactions.
- Any other approved frontend interactions.

Do not introduce fake functionality.

### PHASE 6 — Quality assurance

Check:

- HTML structure.
- CSS responsiveness.
- JavaScript functionality.
- Navigation links.
- Image paths.
- Accessibility.
- SEO metadata.
- Browser-console errors.
- Broken links.
- Horizontal overflow.
- Git status.

Run available validation tools if installed. Do not claim a test passed unless it was actually performed.

### PHASE 7 — Documentation and Git

Update README.md to explain:

- Project purpose.
- Technology stack.
- Directory structure.
- How to run the website locally.
- Where to add product photographs.
- Where to configure official contact and social links.

Use clear Git commit messages when appropriate.

Do not push to GitHub unless the user explicitly authorizes the push.

Never delete Git history, reinitialize the repository, change its remote, or perform destructive Git operations.

---

## 18. IMPORTANT DEVELOPMENT RULES

- Follow the two project documents.
- Follow the approved editorial hero, minimal navigation, and both-category featured products decisions.
- Do not invent business information.
- Do not invent product information.
- Do not download product photographs.
- Do not add a backend.
- Do not introduce unnecessary frameworks.
- Do not create fake functionality.
- Do not overwrite project documentation.
- Do not delete existing assets without authorization.
- Do not push to GitHub without authorization.
- Do not claim successful tests without running them.
- Do not stop after writing a plan; proceed with actual implementation.

If a genuinely essential decision cannot be made from the documents or project context, ask one clear question rather than guessing.

For routine implementation decisions, use professional judgment and continue.

---

## 19. DEFINITION OF DONE

The initial implementation is complete when:

1. The homepage is professionally implemented.
2. The approved design decisions are respected.
3. Abayas and Gowns are both represented.
4. The site is responsive across the specified screen widths.
5. Navigation works.
6. Shared styles and scripts are organized.
7. Other agreed pages are implemented.
8. Placeholder contact information is clearly identified.
9. No invented product or business information appears.
10. No unnecessary backend or framework exists.
11. README.md is accurate.
12. The project has been checked for obvious errors.
13. The Git repository remains intact.

---

## 20. FINAL REPORT

At the end of the implementation, provide a concise but informative report containing:

- What you inspected.
- The two documents you read.
- The architecture established.
- Files and folders created.
- Pages implemented.
- Features implemented.
- Tests actually performed and their results.
- Any remaining issues.
- Any real business information or product photographs still required.
- Git status and whether changes have been committed.

Do not claim that the website is fully production-ready if important assets, business information, or tests are still missing.

**START NOW: Inspect the project, read both documents inside Documents/, understand the specifications, and begin the implementation.**