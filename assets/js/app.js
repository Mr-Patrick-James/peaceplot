(function(){
  const path = (location.pathname || "").split("/").pop() || "index.html";
  const links = document.querySelectorAll(".nav a");
  links.forEach(a => {
    const href = (a.getAttribute("href") || "").split("/").pop();
    if ((href || "") === path) a.classList.add("active");
  });

  document.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-action]");
    if(!btn) return;
    const action = btn.getAttribute("data-action");
    const lot = btn.getAttribute("data-lot") || "";

    if(action === "delete"){
      const ok = confirm(`Delete lot ${lot}?`);
      if(!ok) return;
      const row = btn.closest("tr");
      if(row) row.remove();
      return;
    }

    if(action === "edit"){
      alert(`Edit lot ${lot}`);
    }

    if(action === "add"){
      alert("Add New Cemetery Lot");
    }
  });
})();
