// Graceful enhancement only: browser radio inputs still work without JS.
// A short label and a tiny star-pop make the selection clear on mobile.
document.querySelectorAll('[data-review-form]').forEach((form) => {
  const choices = [...form.querySelectorAll('input[name="rating"]')];
  const caption = form.querySelector('[data-review-caption]');
  const messages = {
    1: '1 yıldız · Beklentimi karşılamadı',
    2: '2 yıldız · Geliştirilebilir',
    3: '3 yıldız · İyi',
    4: '4 yıldız · Çok iyi',
    5: '5 yıldız · Harika',
  };
  const show = () => {
    const chosen = choices.find((input) => input.checked);
    if (caption) caption.textContent = chosen
      ? messages[Number(chosen.value)]
      : 'Yıldızlara dokunarak puan verin';
    if (caption) caption.dataset.selected = chosen ? 'true' : 'false';
  };
  choices.forEach((input) => input.addEventListener('change', show));
  show();
});
