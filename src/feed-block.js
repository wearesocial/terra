(() => {
  const initializeBlock = block => {
    const element = block instanceof jQuery ? block[0] : block;
    if (element === null) {
      return;
    }

    waitForElement('.acf-block-fields', settingsElement => {
      doSettingsChanges(settingsElement);
    });
  };

  const waitForElement = (selector, callback) => {
    const timeout = setInterval(() => {
      const element = document.querySelector(selector);
      if (element) {
        clearInterval(timeout);
        callback(element);
      }
    }, 500);
  };

  const doSettingsChanges = element => {
    if (element === null) {
      return;
    }

    const taxonomies = element.querySelector('div[data-name="terra_taxonomies"] select');
    let tax = taxonomies.value;
    const taxTerms = element.querySelectorAll('div[data-name="terra_term_select"] select optgroup');
    updateTerms(tax, taxTerms);
    taxonomies.addEventListener('change', e => {
      tax = e.currentTarget.value;
      updateTerms(tax, taxTerms);
    });
  };

  const updateTerms = (tax, terms) => {
    if (!tax || !terms[0]) {
      return;
    }

    terms.forEach(term => {
      const label = term.getAttribute('label');
      if (tax === label) {
        term.style.display = 'block';
      } else {
        term.style.display = 'none';
      }
    });
  };

  if (window.acf) {
    window.acf.addAction('render_block_preview/type=terra-feed', initializeBlock);
  }
})();
