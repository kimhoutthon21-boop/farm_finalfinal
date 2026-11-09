(function () {
  'use strict';
  var language = document.documentElement.lang;
  var catalog = window.__translations || {};
  if (language !== 'km') {
    window.pfmTranslate = function (text) { return text; };
    return;
  }

  var entries = Object.keys(catalog).filter(function (source) {
    return source.length >= 8 && /\s/.test(source);
  }).sort(function (a, b) { return b.length - a.length; });
  window.pfmTranslate = function (text) {
    var value = String(text);
    var trimmed = value.trim();
    if (Object.prototype.hasOwnProperty.call(catalog, trimmed)) {
      return value.replace(trimmed, catalog[trimmed]);
    }
    value = entries.reduce(function (result, source) {
      return result.split(source).join(catalog[source]);
    }, value);
    value = value.replace(/\bHouse (\d+)\b/g, 'ទ្រុង $1');
    value = value.replace(/\bDay (\d+) of (\d+)\b/g, 'ថ្ងៃទី $1 នៃ $2');
    value = value.replace(/\b(\d+) of (\d+) reports in\b/g, 'បានដាក់របាយការណ៍ $1 ក្នុងចំណោម $2');
    value = value.replace(/\b(\d{1,2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\b/g, function (_, day, month) {
      return day + ' ' + (catalog[month] || month);
    });
    return value;
  };

  function localize(root) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        var parent = node.parentElement;
        if (!parent || /^(SCRIPT|STYLE|TEXTAREA|CODE|PRE)$/i.test(parent.tagName) || parent.isContentEditable) return NodeFilter.FILTER_REJECT;
        return node.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }
    });
    var nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(function (node) {
      var source = node.nodeValue;
      var translated = window.pfmTranslate(source);
      if (translated !== source) node.nodeValue = translated;
    });

    var attributes = ['aria-label', 'placeholder', 'title', 'alt', 'data-confirm', 'data-confirm-title', 'data-confirm-ok', 'data-title', 'data-label', 'data-tip'];
    var elements = root.nodeType === Node.ELEMENT_NODE ? [root].concat(Array.prototype.slice.call(root.querySelectorAll('*'))) : [];
    elements.forEach(function (element) {
      attributes.forEach(function (attribute) {
        if (element.hasAttribute(attribute)) {
          var source = element.getAttribute(attribute);
          var translated = window.pfmTranslate(source);
          if (translated !== source) element.setAttribute(attribute, translated);
        }
      });
      if (element.matches('input[type="submit"], input[type="button"]')) {
        element.value = window.pfmTranslate(element.value);
      }
    });
  }

  localize(document.body);
  new MutationObserver(function (records) {
    records.forEach(function (record) {
      if (record.type === 'attributes') {
        var source = record.target.getAttribute(record.attributeName);
        var translated = window.pfmTranslate(source);
        if (translated !== source) record.target.setAttribute(record.attributeName, translated);
        return;
      }
      if (record.type === 'characterData') {
        var source = record.target.nodeValue;
        var translated = window.pfmTranslate(source);
        if (translated !== source) record.target.nodeValue = translated;
      } else {
        record.addedNodes.forEach(function (node) {
          if (node.nodeType === Node.ELEMENT_NODE) localize(node);
          else if (node.nodeType === Node.TEXT_NODE) {
            var source = node.nodeValue;
            var translated = window.pfmTranslate(source);
            if (translated !== source) node.nodeValue = translated;
          }
        });
      }
    });
  }).observe(document.body, {
    childList: true,
    characterData: true,
    attributes: true,
    attributeFilter: ['aria-label', 'placeholder', 'title', 'alt', 'data-confirm', 'data-confirm-title', 'data-confirm-ok', 'data-title', 'data-label', 'data-tip'],
    subtree: true
  });
})();
