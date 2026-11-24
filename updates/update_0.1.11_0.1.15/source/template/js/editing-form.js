/* Native Studio keeps its own validation and submission. This adapter changes
 * only the presentation and missing/invalid paused-language title inputs. */
(function (window) {
  'use strict';
  if (window.MyUnaTranslatorEditingForms) {
    window.MyUnaTranslatorEditingForms.scan();
    return;
  }

  const document = window.document;
  const marker = 'my_una_translator_editing_session';
  const forms = new WeakMap();
  const supported = {
    'adm-bp-block-edit': ['title'],
    'adm-bp-page-create': ['title', 'title_system'],
    'adm-bp-page-edit': ['title', 'title_system']
  };
  const languageCode = /^[a-zA-Z0-9_-]{1,32}$/;

  function configuration(value) {
    if (!value || !supported[value.form_id] || !Number.isSafeInteger(value.session_id) || value.session_id < 1 ||
        typeof value.source !== 'string' || !languageCode.test(value.source) || !Array.isArray(value.languages) ||
        !['unicode', 'bytes'].includes(value.length_mode)) return null;
    const seen = new Set();
    for (const language of value.languages) {
      if (!language || typeof language.code !== 'string' || !languageCode.test(language.code) || seen.has(language.code) ||
          ![0, 1, false, true].includes(language.enabled)) return null;
      seen.add(language.code);
    }
    if (!value.languages.some(language => language.code === value.source && Boolean(language.enabled))) return null;
    return {form_id: value.form_id, session_id: value.session_id, source: value.source,
      languages: value.languages, fields: supported[value.form_id], length_mode: value.length_mode};
  }

  function unique(nodes, predicate) {
    const matches = Array.from(nodes).filter(predicate);
    return matches.length === 1 ? matches[0] : null;
  }

  function translatedField(form, name, config) {
    const row = unique(form.querySelectorAll('[id]'), element => element.id === 'bx-form-element-' + name);
    if (!row) return null;
    const wrapper = unique(row.querySelectorAll('.bx-form-input-translatable'), () => true);
    if (!wrapper) return null;
    const key = unique(wrapper.querySelectorAll('input'), input => input.name === name && input.type === 'hidden');
    if (!key || key.disabled) return null;
    const inputs = new Map();
    for (const language of config.languages) {
      const input = unique(wrapper.querySelectorAll('input'), element => element.name === name + '-' + language.code && element.type === 'text');
      if (!input || input.disabled) return null;
      const translation = input.closest('.bx-form-input-translation');
      if (!translation || !wrapper.contains(translation) || !translation.classList.contains('bx-form-input-translation-' + name + '-' + language.code)) return null;
      inputs.set(language.code, {input, translation});
    }
    return {name, row, wrapper, inputs};
  }

  // UNA get_mb_len uses mb_strlen, or UTF-8 byte length without mbstring.
  // Ill-formed UTF-16 has no exact UTF-8 counterpart: do not propagate it.
  function nativeLength(value, mode) {
    let count = 0;
    for (let index = 0; index < value.length; ++index) {
      let point = value.charCodeAt(index);
      if (point >= 0xd800 && point <= 0xdbff) {
        const low = value.charCodeAt(++index);
        if (!(low >= 0xdc00 && low <= 0xdfff)) return null;
        point = 0x10000 + ((point - 0xd800) << 10) + low - 0xdc00;
      } else if (point >= 0xdc00 && point <= 0xdfff) return null;
      count += mode === 'unicode' ? 1 : point < 0x80 ? 1 : point < 0x800 ? 2 : point < 0x10000 ? 3 : 4;
    }
    return count;
  }

  function validTitle(value, mode) {
    const length = nativeLength(value, mode);
    return length !== null && length >= 3 && length <= 100;
  }

  function showSource(field, config) {
    const sourceOption = unique(field.row.querySelectorAll('[id]'), element => element.id === 'bx-form-language-' + field.name + '-' + config.source + '-pas');
    const sourceLink = sourceOption && sourceOption.querySelector('a');
    const currentLink = field.wrapper.querySelector('.bx-form-input-language-current a');
    if (sourceLink && currentLink) currentLink.replaceChildren(...Array.from(sourceLink.childNodes, node => node.cloneNode(true)));
    for (const language of config.languages) {
      const source = language.code === config.source;
      const translated = field.inputs.get(language.code).translation;
      translated.style.display = source ? '' : 'none';
      for (const state of ['act', 'pas']) {
        const option = unique(field.row.querySelectorAll('[id]'), element => element.id === 'bx-form-language-' + field.name + '-' + language.code + '-' + state);
        if (!option) continue;
        // Selector choices are not form controls. Removing paused choices
        // prevents native language switching from making them visible again.
        if (!language.enabled && !source) option.remove();
        else option.style.display = (source === (state === 'act')) ? '' : 'none';
      }
    }
  }

  function mount(form, config) {
    if (forms.has(form)) return;
    const fields = config.fields.map(name => translatedField(form, name, config));
    if (fields.some(field => field === null)) return;
    const markers = Array.from(form.elements).filter(input => input.name === marker);
    if (markers.length > 1 || (markers.length === 1 &&
        (markers[0].type !== 'hidden' || markers[0].disabled || markers[0].value !== String(config.session_id)))) return;
    if (!markers.length) {
      const session = document.createElement('input');
      session.type = 'hidden'; session.name = marker; session.value = String(config.session_id);
      form.append(session);
    }
    for (const field of fields) showSource(field, config);

    const prepare = function () {
      // Never remove, disable, or synthesize a native language input. All
      // original values, optional translations, and CSRF remain submitted.
      for (const field of fields) {
        const source = field.inputs.get(config.source).input.value;
        if (!validTitle(source, config.length_mode)) continue;
        for (const language of config.languages) {
          if (language.enabled || language.code === config.source) continue;
          const input = field.inputs.get(language.code).input;
          if (!validTitle(input.value, config.length_mode)) input.value = source;
        }
      }
    };
    form.addEventListener('submit', prepare, true);
    if (window.jQuery) window.jQuery(form).on('form-pre-serialize.myUnaTranslatorEditing', prepare);
    forms.set(form, {session: config.session_id, prepare});
  }

  function scan() {
    for (const element of document.querySelectorAll('script.my-una-translator-editing-config[type="application/json"]')) {
      let config;
      try { config = configuration(JSON.parse(element.textContent)); } catch (error) { continue; }
      if (!config || !element.parentElement) continue;
      const form = unique(element.parentElement.querySelectorAll('form'), node => node.getAttribute('id') === config.form_id);
      if (form) mount(form, config);
    }
  }

  window.MyUnaTranslatorEditingForms = {scan};
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan, {once: true});
  scan();
  // Native Studio adds and replaces AJAX popup markup after page load.
  if (window.MutationObserver) new window.MutationObserver(scan).observe(document.documentElement, {childList: true, subtree: true});
})(window);
