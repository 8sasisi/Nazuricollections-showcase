document.addEventListener("submit", (event) => {
  const prompt = event.target.dataset.confirm;
  if (prompt && !window.confirm(prompt)) {
    event.preventDefault();
  }
});
