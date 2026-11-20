'use strict';

// Список выбранных вложений в форме
const filesInput = document.querySelector('input[name="files[]"]');
if (filesInput) {
  const list = document.getElementById('file-list');
  filesInput.addEventListener('change', () => {
    list.textContent = '';
    for (const file of filesInput.files) {
      const li = document.createElement('li');
      li.textContent = file.name + ' (' + formatSize(file.size) + ')';
      list.appendChild(li);
    }
  });
}

function formatSize(bytes) {
  const units = ['Б', 'КБ', 'МБ', 'ГБ'];
  let i = 0;
  let v = bytes;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i++;
  }
  return (i ? v.toFixed(1) : String(v)) + ' ' + units[i];
}

// Копирование ссылки в буфер обмена (clipboard API + fallback)
document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy]');
  if (!button) return;
  const target = document.querySelector(button.dataset.copy);
  const text = target ? target.textContent : (button.dataset.copyText || '');
  try {
    await navigator.clipboard.writeText(text);
  } catch (err) {
    copyFallback(text);
  }
  const old = button.textContent;
  button.textContent = 'Скопировано!';
  setTimeout(() => {
    button.textContent = old;
  }, 2000);
});

function copyFallback(text) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
  } catch (err) {
    // буфер обмена недоступен — оставляем текст выделенным для ручного копирования
  }
  ta.remove();
}

// Переключатель темы: тёмная — по умолчанию, светлая включается
// атрибутом data-theme="light"; выбор запоминается в localStorage.
(function () {
  const button = document.getElementById('theme-toggle');
  if (!button) return;
  const THEME_KEY = 'onetime-theme';
  button.addEventListener('click', () => {
    const root = document.documentElement;
    const toLight = root.getAttribute('data-theme') !== 'light';
    if (toLight) {
      root.setAttribute('data-theme', 'light');
    } else {
      root.removeAttribute('data-theme');
    }
    try {
      localStorage.setItem(THEME_KEY, toLight ? 'light' : 'dark');
    } catch (err) {
      // localStorage недоступен — тема живёт только до перезагрузки
    }
  });
})();
