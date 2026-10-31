function applyHeaderThemeIcon() {
  const darkCss = document.getElementById('dark-theme');
  const icon = document.querySelector('#header-theme-toggle i');
  if (!darkCss || !icon) {
    return;
  }
  icon.className = 'fa-solid ' + (darkCss.disabled ? 'fa-moon' : 'fa-sun');
}

function toggleHeaderTheme() {
  const darkThemeCss = document.getElementById('dark-theme');
  const toggle = document.getElementById('header-theme-toggle');
  if (!darkThemeCss || !toggle) {
    return;
  }

  const nextIsDark = darkThemeCss.disabled;
  const themeCode = nextIsDark ? 1 : 0;
  const themeChoice = nextIsDark ? 'dark' : 'light';

  darkThemeCss.disabled = !nextIsDark;

  const existingClasses = document.body.className.split(' ').filter(function (cls) {
    return cls !== 'dark' && cls !== 'light';
  });
  document.body.className = existingClasses.concat(themeChoice).join(' ');

  document.cookie = 'theme=' + themeChoice + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax';

  const themeColorMetaTag = document.querySelector('meta[name="theme-color"]');
  if (themeColorMetaTag) {
    themeColorMetaTag.setAttribute('content', themeChoice === 'dark' ? '#12151C' : '#FFFFFF');
  }

  applyHeaderThemeIcon();

  toggle.disabled = true;
  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ theme: themeCode }),
  })
    .catch(function () { })
    .finally(function () {
      toggle.disabled = false;
    });
}

document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('header-theme-toggle');
  if (toggle) {
    toggle.addEventListener('click', toggleHeaderTheme);
  }
  applyHeaderThemeIcon();
});
