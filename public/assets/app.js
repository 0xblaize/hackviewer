document.querySelectorAll('[data-deadline]').forEach((element) => {
  const deadline = Date.parse(element.dataset.deadline || '');
  if (Number.isNaN(deadline)) return;
  const render = () => {
    const remaining = deadline - Date.now();
    if (remaining <= 0) { element.textContent = 'Ended'; return; }
    const minutes = Math.ceil(remaining / 60000);
    if (minutes < 60) element.textContent = `${minutes}m left`;
    else if (minutes < 1440) element.textContent = `${Math.floor(minutes / 60)}h ${minutes % 60}m left`;
    else element.textContent = `${Math.floor(minutes / 1440)}d ${Math.floor((minutes % 1440) / 60)}h left`;
  };
  render();
  window.setInterval(render, 60000);
});
