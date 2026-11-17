// Progressive enhancement: no JavaScript is needed to render blog articles.
const addSource = document.querySelector('[data-blog-source-add]');
if (addSource) {
  const container = document.querySelector('[data-blog-sources]');
  const template = document.querySelector('#blog-source-template');
  const names = [...container.querySelectorAll('[name^="sources["]')].map(el => Number(el.name.match(/^sources\[(\d+)\]/)?.[1] || 0));
  let nextIndex = Math.max(0, ...names) + 1;
  addSource.addEventListener('click', () => {
    if (!template || container.querySelectorAll('[data-blog-source-row]').length >= 20) return;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
    const row = wrapper.firstElementChild;
    if (row) { container.append(row); row.querySelector('input')?.focus(); }
  });
  container?.addEventListener('click', event => {
    const button = event.target.closest('[data-blog-source-remove]');
    if (button) button.closest('[data-blog-source-row]')?.remove();
  });
}
document.querySelectorAll('[data-blog-new-block]').forEach(form => {
  const type = form.querySelector('[data-blog-block-type]');
  const text = form.querySelector('[data-blog-block-text]');
  const media = form.querySelector('[data-blog-block-images]');
  const textarea = text?.querySelector('textarea');
  const files = form.querySelector('[data-blog-block-file]');
  if (!type || !text || !media || !textarea || !files) return;
  const update = () => {
    const isMedia = type.value === 'image' || type.value === 'slider';
    text.hidden = isMedia;
    media.hidden = !isMedia;
    textarea.disabled = isMedia;
    textarea.required = !isMedia;
    files.disabled = !isMedia;
    files.required = isMedia;
    files.multiple = type.value === 'slider';
    media.querySelectorAll('input:not([type=file])').forEach(input => input.disabled = !isMedia);
  };
  type.addEventListener('change', update);
  update();
});
document.querySelectorAll('[data-blog-gallery]').forEach(gallery => {
  const track = gallery.querySelector('[data-blog-gallery-track]');
  const prev = gallery.querySelector('[data-blog-gallery-prev]');
  const next = gallery.querySelector('[data-blog-gallery-next]');
  const count = gallery.querySelector('[data-blog-gallery-current]');
  if (!track || !prev || !next) return;
  const slides = track.children.length;
  const update = () => {
    const index = Math.min(slides - 1, Math.max(0, Math.round(track.scrollLeft / Math.max(1, track.clientWidth))));
    if (count) count.textContent = String(index + 1);
    prev.disabled = index === 0;
    next.disabled = index >= slides - 1;
  };
  const move = direction => {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    track.scrollBy({left: direction * track.clientWidth, behavior: reduce ? 'instant' : 'smooth'});
  };
  prev.addEventListener('click', () => move(-1));
  next.addEventListener('click', () => move(1));
  track.addEventListener('scroll', update, {passive: true});
  if ('ResizeObserver' in window) new ResizeObserver(update).observe(track);
  else window.addEventListener('resize', update, {passive: true});
  update();
});
