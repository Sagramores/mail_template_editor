/**
 * Mail Template Editor - back-office UI.
 * Content is sent to the server base64-encoded (UTF-8 bytes) so that no filter,
 * proxy or PHP input sanitising can alter Greek characters or HTML.
 */
(function ($) {
  'use strict';

  $(function () {
    var cfgEl = document.getElementById('mte-config');
    if (!cfgEl) {
      return;
    }
    var cfg = JSON.parse(cfgEl.textContent);
    var t = cfg.i18n;
    var state = {
      layout: cfg.layout,
      settings: cfg.settings,
      social: cfg.social || {},
      multilang: cfg.multilang || {},
      footerLang: cfg.idLang,
      previewLang: cfg.idLang,
      contentLang: cfg.idLang,
      filesLang: cfg.idLang,
      templates: [],
      current: null,
      filter: 'all',
      dirty: false,
      files: [],
      currentFile: null,
      editor: null,
    };

    /* ---------------- helpers ---------------- */

    function b64(str) {
      var bytes = new TextEncoder().encode(str || '');
      var bin = '';
      for (var i = 0; i < bytes.length; i += 0x8000) {
        bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
      }
      return btoa(bin);
    }

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    function toast(msg, isError) {
      var $t = $('#mte-toast');
      $t.text(msg).toggleClass('mte-toast-error', !!isError).addClass('mte-toast-show');
      clearTimeout(toast.timer);
      toast.timer = setTimeout(function () { $t.removeClass('mte-toast-show'); }, isError ? 6000 : 2500);
    }

    function api(action, data) {
      data = data || {};
      data.action = action;
      return $.ajax({ url: cfg.ajaxUrl + '&action=' + encodeURIComponent(action), type: 'POST', data: data, dataType: 'json' })
        .then(function (res) {
          if (!res || !res.ok) {
            var msg = (res && res.message) || t.error;
            toast(msg, true);
            return $.Deferred().reject(msg).promise();
          }
          return res;
        }, function (xhr) {
          toast(t.error + ' ' + (xhr.status || '') + ' ' + (xhr.responseText || '').replace(/<[^>]+>/g, ' ').substr(0, 200), true);
          return $.Deferred().reject().promise();
        });
    }

    function writeFrame(iframe, html) {
      var doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.open();
      doc.write(html);
      doc.close();
      setTimeout(function () {
        try {
          iframe.style.height = Math.max(500, doc.documentElement.scrollHeight + 20) + 'px';
        } catch (e) {}
      }, 120);
    }

    function langOptions($select, selected) {
      $select.empty();
      cfg.languages.forEach(function (l) {
        $select.append($('<option>').val(l.id).text(l.name + ' (' + l.iso + ')').prop('selected', l.id === selected));
      });
    }

    function debounce(fn, ms) {
      var timer;
      return function () {
        var args = arguments, self = this;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(self, args); }, ms);
      };
    }

    /* ---------------- DESIGN TAB ---------------- */

    var $fontSelect = $('#mte-font-select');
    $.each(cfg.fonts, function (key, label) {
      $fontSelect.append($('<option>').val(key).text(label));
    });

    var $social = $('#mte-social');
    $.each(cfg.socialNetworks, function (key, label) {
      $social.append(
        '<div class="input-group mte-social-row"><span class="input-group-addon">' + esc(label) + '</span>' +
        '<input type="url" class="form-control" data-mte-social="' + esc(key) + '" placeholder="https://"></div>'
      );
    });

    langOptions($('#mte-footer-lang'), state.footerLang);
    langOptions($('#mte-preview-lang'), state.previewLang);
    $('[data-mte-global="mode"]').val(String(cfg.mode));
    $('[data-mte-global="skip"]').val(cfg.skip);
    $('.mte-no-logo').toggleClass('hidden', !!cfg.hasLogo);

    function layoutName(key) {
      if (cfg.layoutNames[key]) {
        return cfg.layoutNames[key];
      }
      var found = cfg.customLayouts.filter(function (l) { return l.key === key; })[0];
      return found ? found.name : key;
    }

    function isCustom(key) {
      return /^custom_\d+$/.test(key || '');
    }

    function renderLayoutCards() {
      var $box = $('#mte-layouts').empty();
      var keys = ['prestashop', 'classic', 'modern', 'minimal'].concat(cfg.customLayouts.map(function (l) { return l.key; }));
      keys.forEach(function (key) {
        var custom = isCustom(key);
        var $card = $('<div class="mte-layout-card" role="button" tabindex="0">').attr('data-layout', key).toggleClass('active', key === state.layout);
        $card.append('<span class="mte-thumb mte-thumb-' + (custom ? 'custom' : key) + '"><i></i><b></b><em></em><em></em><u></u></span>');
        $card.append($('<span class="mte-layout-name">').text(layoutName(key)));
        if (custom) {
          $card.append('<span class="mte-card-tools"><button type="button" class="mte-card-btn" data-mte-edit-layout="' + esc(key) + '" title="' + esc(t.edit) + '"><i class="icon-pencil"></i></button>'
            + '<button type="button" class="mte-card-btn" data-mte-delete-layout="' + esc(key) + '" title="' + esc(t.delete) + '"><i class="icon-trash"></i></button></span>');
        }
        $box.append($card);
      });
    }

    function fillDesignForm() {
      if (!state.settings[state.layout]) {
        state.settings[state.layout] = $.extend({}, isCustom(state.layout) ? cfg.defaults.custom : cfg.defaults[state.layout]);
      }
      var s = state.settings[state.layout];
      renderLayoutCards();
      $('[data-mte-setting]').each(function () {
        var key = $(this).data('mte-setting');
        if (this.type === 'checkbox') {
          this.checked = !!parseInt(s[key], 10);
        } else {
          $(this).val(s[key]);
        }
      });
      $('[data-mte-social]').each(function () {
        $(this).val(state.social[$(this).data('mte-social')] || '');
      });
      fillMultilang();
    }

    function fillMultilang() {
      $('[data-mte-ml]').each(function () {
        var field = $(this).data('mte-ml');
        var values = state.multilang[field] || {};
        $(this).val(values[state.footerLang] || '');
      });
    }

    function readDesignForm() {
      var s = state.settings[state.layout];
      $('[data-mte-setting]').each(function () {
        var key = $(this).data('mte-setting');
        s[key] = this.type === 'checkbox' ? (this.checked ? 1 : 0) : (this.type === 'number' ? parseFloat(this.value) : this.value);
      });
      $('[data-mte-social]').each(function () {
        var v = $.trim(this.value);
        if (v) {
          state.social[$(this).data('mte-social')] = v;
        } else {
          delete state.social[$(this).data('mte-social')];
        }
      });
      $('[data-mte-ml]').each(function () {
        var field = $(this).data('mte-ml');
        state.multilang[field] = state.multilang[field] || {};
        state.multilang[field][state.footerLang] = this.value;
      });
    }

    function designPayload(idLang) {
      readDesignForm();
      return {
        layout: state.layout,
        settings: JSON.stringify(state.settings[state.layout]),
        social: JSON.stringify(state.social),
        multilang: b64(JSON.stringify(state.multilang)),
        id_lang: idLang
      };
    }

    function previewTemplateParams(data) {
      var v = ($('#mte-preview-template').val() || 'core/order_conf').split('/');
      data.preview_module = v[0];
      data.preview_template = v[1];
      return data;
    }

    function loadPreviewTemplates() {
      api('listTemplates', { id_lang: state.previewLang }).then(function (res) {
        var $sel = $('#mte-preview-template');
        var current = $sel.val() || 'core/order_conf';
        $sel.empty();
        res.templates.forEach(function (tpl) {
          if (!tpl.translated) {
            return;
          }
          $sel.append($('<option>').val(tpl.key).text((tpl.module === 'core' ? '' : tpl.module + ' / ') + tpl.template));
        });
        $sel.val(current);
        if (!$sel.val()) {
          $sel.prop('selectedIndex', 0);
        }
      });
    }

    var refreshDesignPreview = debounce(function () {
      var data = previewTemplateParams(designPayload(state.previewLang));
      data.content = '';
      api('preview', data).then(function (res) {
        writeFrame(document.getElementById('mte-design-preview'), res.html);
      });
    }, 350);

    $('#mte-layouts').on('click keypress', '.mte-layout-card', function (e) {
      if ($(e.target).closest('.mte-card-btn').length) {
        return;
      }
      readDesignForm();
      state.layout = $(this).data('layout');
      fillDesignForm();
      refreshDesignPreview();
    });
    $('#mte-preview-template').on('change', refreshDesignPreview);
    $('#mte-tab-design').on('input change', '[data-mte-setting], [data-mte-social], [data-mte-ml]', refreshDesignPreview);
    $('#mte-footer-lang').on('change', function () {
      readDesignForm();
      state.footerLang = parseInt(this.value, 10);
      fillMultilang();
    });
    $('#mte-preview-lang').on('change', function () {
      state.previewLang = parseInt(this.value, 10);
      loadPreviewTemplates();
      refreshDesignPreview();
    });
    $('[data-mte-device]').on('click', function () {
      $('[data-mte-device]').removeClass('active');
      $(this).addClass('active');
      $('#mte-tab-design .mte-frame-wrap').toggleClass('mte-mobile', $(this).data('mte-device') === 'mobile');
    });

    $('#mte-reset-design').on('click', function () {
      state.settings[state.layout] = $.extend({}, isCustom(state.layout) ? cfg.defaults.custom : cfg.defaults[state.layout]);
      fillDesignForm();
      refreshDesignPreview();
    });

    $('#mte-save-design').on('click', function () {
      var data = designPayload(state.previewLang);
      data.mode = $('[data-mte-global="mode"]').val();
      data.skip = $('[data-mte-global="skip"]').val();
      api('saveDesign', data).then(function () { toast(t.saved); });
    });

    fillDesignForm();
    loadPreviewTemplates();
    refreshDesignPreview();

    /* ---------------- CONTENT TAB ---------------- */

    langOptions($('#mte-content-lang'), state.contentLang);
    $('#mte-test-email').val(cfg.employeeEmail);

    function initEditor() {
      if (!cfg.tinymce || typeof window.tinymce === 'undefined') {
        $('[data-mte-mode="text"]').hide();
        return $.Deferred().resolve().promise();
      }
      var d = $.Deferred();
      var s = state.settings[state.layout];
      window.tinymce.init({
        selector: '#mte-editor',
        height: 700,
        menubar: false,
        branding: false,
        skin: cfg.tinymceSkin,
        plugins: 'code table link image lists textcolor colorpicker',
        toolbar: 'formatselect | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image table | removeformat code',
        // Keep characters as they are: no &alpha; / &#945; conversions (the Greek bug of the core editor).
        entity_encoding: 'raw',
        // Keep the HTML as written (custom styles, tables, attributes).
        verify_html: false,
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        valid_children: '+body[style],+a[div|p|table|h1|h2|h3|span|img]',
        convert_urls: false,
        relative_urls: false,
        remove_script_host: false,
        forced_root_block: 'p',
        content_style: 'body{font-family:Arial,sans-serif;font-size:' + (s.font_size || 14) + 'px;padding:10px;} p,td,th,li,span,div{font-size:inherit;} .mte-button{display:inline-block;padding:10px 22px;background:' + s.button_bg + ';color:' + s.button_text + ';border-radius:4px;text-decoration:none;} .mte-box{border:1px solid #ddd;padding:12px;margin:0 0 12px;}',
        setup: function (ed) {
          state.editor = ed;
          ed.on('change keyup', function () { state.dirty = true; });
          ed.on('init', function () { d.resolve(); });
        }
      });
      setTimeout(function () { d.resolve(); }, 4000);
      return d.promise();
    }

    /*
     * Three ways to edit the same content:
     *  visual: inside the design, as the customer will see it (GrapesJS, only the content is editable)
     *  text:   TinyMCE
     *  html:   source code
     */
    state.mode = (function () {
      try {
        return window.localStorage.getItem('mte_mode') || 'visual';
      } catch (e) {
        return 'visual';
      }
    })();

    function getContent() {
      if (state.mode === 'visual' && visual.ready) {
        return visualGetContent();
      }
      if (state.mode === 'text' && state.editor) {
        return state.editor.getContent();
      }
      return $('#mte-editor').val();
    }

    function setContent(html) {
      $('#mte-editor').val(html);
      if (state.mode === 'text' && state.editor) {
        state.editor.setContent(html);
      } else if (state.mode === 'visual') {
        visualLoad(html);
      }
    }

    function applyMode(mode, html) {
      if (mode === 'text' && !state.editor) {
        mode = 'html';
      }
      if (mode === 'visual' && !cfg.builder) {
        mode = 'text';
      }
      state.mode = mode;
      try {
        window.localStorage.setItem('mte_mode', mode);
      } catch (e) {}
      $('[data-mte-mode]').removeClass('active').filter('[data-mte-mode="' + mode + '"]').addClass('active');
      $('#mte-visual').prop('hidden', mode !== 'visual');
      $('#mte-insert-button, #mte-insert-box').toggle(mode !== 'visual');
      if (state.editor) {
        if (mode === 'text') {
          state.editor.show();
        } else {
          state.editor.hide();
        }
      }
      $('#mte-editor').toggle(mode === 'html');
      setContent(html);
    }

    $('[data-mte-mode]').on('click', function () {
      var mode = $(this).data('mte-mode');
      if (mode === state.mode) {
        return;
      }
      applyMode(mode, getContent());
    });

    function insertHtml(html) {
      if (state.mode === 'visual' && visual.ready) {
        visualInsert(html);
      } else if (state.mode === 'text' && state.editor) {
        state.editor.insertContent(html);
      } else {
        var el = document.getElementById('mte-editor');
        var start = el.selectionStart || 0;
        el.value = el.value.substr(0, start) + html + el.value.substr(el.selectionEnd || start);
        el.selectionStart = el.selectionEnd = start + html.length;
        el.focus();
      }
      state.dirty = true;
    }

    $('#mte-editor').on('input', function () { state.dirty = true; });

    function loadTemplates() {
      api('listTemplates', { id_lang: state.contentLang }).then(function (res) {
        state.templates = res.templates;
        renderTemplateList();
      });
    }

    function renderTemplateList() {
      var q = $.trim($('#mte-template-search').val()).toLowerCase();
      var $list = $('#mte-template-list').empty();
      var group = null;
      state.templates.forEach(function (tpl) {
        if (state.filter === 'custom' && !tpl.custom) {
          return;
        }
        if (q && tpl.key.toLowerCase().indexOf(q) === -1) {
          return;
        }
        if (tpl.module !== group) {
          group = tpl.module;
          $list.append('<div class="mte-group">' + esc(group === 'core' ? 'PrestaShop' : group) + '</div>');
        }
        var badges = '';
        if (tpl.custom) {
          badges += '<span class="label ' + (tpl.active ? 'label-success' : 'label-default') + '">' + esc(tpl.active ? t.customised : t.disabled) + '</span>';
        }
        if (!tpl.translated) {
          badges += '<span class="label label-warning" title="' + esc(t.notTranslated) + '">!</span>';
        }
        var $item = $('<a href="#" class="mte-item">').attr('data-key', tpl.key).html(esc(tpl.template) + ' ' + badges);
        if (state.current && state.current.key === tpl.key) {
          $item.addClass('active');
        }
        $list.append($item);
      });
    }

    $('#mte-template-search').on('input', renderTemplateList);
    $('[data-mte-filter]').on('click', function (e) {
      e.preventDefault();
      $('[data-mte-filter]').removeClass('active');
      $(this).addClass('active');
      state.filter = $(this).data('mte-filter');
      renderTemplateList();
    });
    $('#mte-content-lang').on('change', function () {
      if (state.dirty && !window.confirm(t.confirmLeave)) {
        $(this).val(state.contentLang);
        return;
      }
      state.contentLang = parseInt(this.value, 10);
      state.dirty = false;
      if (state.current) {
        openTemplate(state.current);
      }
      loadTemplates();
    });

    function tplParams() {
      return { module_key: state.current.module, template: state.current.template, id_lang: state.contentLang };
    }

    function openTemplate(tpl) {
      state.current = tpl;
      $('#mte-template-list .mte-item').removeClass('active').filter('[data-key="' + tpl.key + '"]').addClass('active');
      api('loadTemplate', tplParams()).then(function (res) {
        $('#mte-editor-body').removeClass('hidden');
        $('#mte-editing-title').text((tpl.module === 'core' ? '' : tpl.module + ' / ') + tpl.template);
        $('#mte-editing-file').text(res.file ? ' — ' + res.file : '');
        $('#mte-subject').val(res.subject);
        $('#mte-active').prop('checked', res.active);
        $('#mte-delete-template').toggle(res.custom);
        $('#mte-content-preview-wrap').addClass('hidden');
        var $vars = $('#mte-vars').empty();
        res.variables.forEach(function (v) {
          $vars.append($('<a href="#" class="mte-var">').text(v));
        });
        setContent(res.content);
        state.dirty = false;
        $('#mte-normalize').prop('checked', state.layout !== 'prestashop');
        if (!res.custom) {
          importOriginal(false);
        }
      });
    }

    $('#mte-template-list').on('click', '.mte-item', function (e) {
      e.preventDefault();
      if (state.dirty && !window.confirm(t.confirmLeave)) {
        return;
      }
      var key = $(this).data('key');
      var tpl = state.templates.filter(function (x) { return x.key === key; })[0];
      if (tpl) {
        openTemplate(tpl);
      }
    });

    $('#mte-vars').on('click', '.mte-var', function (e) {
      e.preventDefault();
      insertHtml($(this).text());
    });

    function importOriginal(ask) {
      if (ask && $.trim(getContent()) !== '' && !window.confirm(t.confirmImport)) {
        return;
      }
      var data = tplParams();
      data.normalize = $('#mte-normalize').is(':checked') ? 1 : 0;
      api('importOriginal', data).then(function (res) {
        setContent(res.content);
        state.dirty = !!ask;
      });
    }
    $('#mte-import').on('click', function () { importOriginal(true); });

    $('#mte-insert-button').on('click', function () {
      var text = window.prompt(t.buttonText, '');
      if (!text) {
        return;
      }
      var url = window.prompt(t.buttonUrl, '{shop_url}') || '{shop_url}';
      insertHtml('<p style="text-align:center"><a class="mte-button" href="' + esc(url) + '">' + esc(text) + '</a></p>');
    });
    $('#mte-insert-box').on('click', function () {
      insertHtml('<div class="mte-box"><p>...</p></div>');
    });

    $('#mte-repair').on('click', function () {
      var before = getContent();
      api('repair', { content: b64(before) }).then(function (res) {
        if (res.changed) {
          setContent(res.content);
          state.dirty = true;
          toast(t.repaired);
        } else {
          toast(t.nothingToRepair);
        }
      });
    });

    $('#mte-save-template').on('click', function () {
      if (!state.current) {
        return;
      }
      var data = tplParams();
      data.content = b64(getContent());
      data.subject = $('#mte-subject').val();
      data.active = $('#mte-active').is(':checked') ? 1 : 0;
      api('saveTemplate', data).then(function () {
        state.dirty = false;
        toast(t.saved);
        $('#mte-delete-template').show();
        loadTemplates();
      });
    });

    $('#mte-preview-template').on('click', function () {
      if (!state.current) {
        return;
      }
      api('preview', { content: b64(getContent()), id_lang: state.contentLang, subject: $('#mte-subject').val() }).then(function (res) {
        $('#mte-content-preview-wrap').removeClass('hidden');
        writeFrame(document.getElementById('mte-content-preview'), res.html);
      });
    });

    $('#mte-send-test').on('click', function () {
      if (!state.current) {
        return;
      }
      var data = tplParams();
      data.email = $('#mte-test-email').val();
      api('sendTest', data).then(function () { toast(t.sent); });
    });

    $('#mte-delete-template').on('click', function () {
      if (!state.current || !window.confirm(t.confirmDelete)) {
        return;
      }
      api('deleteTemplate', tplParams()).then(function () {
        toast(t.saved);
        loadTemplates();
        openTemplate(state.current);
      });
    });

    $('#mte-bulk-import').on('click', function () {
      if (!window.confirm(t.confirmBulk)) {
        return;
      }
      var $btn = $(this).prop('disabled', true);
      api('bulkImport', { id_lang: state.contentLang }).then(function (res) {
        toast(t.imported + ': ' + res.count);
        loadTemplates();
      }).always(function () { $btn.prop('disabled', false); });
    });

    window.addEventListener('beforeunload', function (e) {
      if (state.dirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    var contentInit = false;
    $('a[href="#mte-tab-content"]').on('shown.bs.tab click', function () {
      if (contentInit) {
        return;
      }
      contentInit = true;
      initEditor().then(function () {
        applyMode(state.mode, $('#mte-editor').val());
        loadTemplates();
      });
    });

    /* ---------------- FILES TAB ---------------- */

    langOptions($('#mte-files-lang'), state.filesLang);

    function loadFiles() {
      api('listFiles', { id_lang: state.filesLang }).then(function (res) {
        state.files = res.files;
        renderFiles();
      });
    }

    function renderFiles() {
      var q = $.trim($('#mte-file-search').val()).toLowerCase();
      var $list = $('#mte-file-list').empty();
      var group = null;
      state.files.forEach(function (f) {
        if (q && f.path.toLowerCase().indexOf(q) === -1) {
          return;
        }
        if (f.module !== group) {
          group = f.module;
          $list.append('<div class="mte-group">' + esc(group === 'core' ? 'PrestaShop' : group) + '</div>');
        }
        var name = f.path.split('/').pop();
        var $a = $('<a href="#" class="mte-item">').attr('data-path', f.path).attr('title', f.path).text(name);
        if (state.currentFile === f.path) {
          $a.addClass('active');
        }
        $list.append($a);
      });
    }

    function renderBackups(list) {
      var $b = $('#mte-backups').empty();
      if (!list.length) {
        $b.append('<tr><td class="text-muted">' + esc(t.noBackups) + '</td></tr>');
        return;
      }
      list.forEach(function (b) {
        $b.append('<tr><td>' + esc(b.date) + '</td><td>' + esc(Math.round(b.size / 102.4) / 10) + ' KB</td><td class="text-right"><button type="button" class="btn btn-default btn-xs" data-mte-restore="' + esc(b.name) + '">' + esc(t.restore) + '</button></td></tr>');
      });
    }

    $('#mte-file-search').on('input', renderFiles);
    $('#mte-files-lang').on('change', function () {
      state.filesLang = parseInt(this.value, 10);
      loadFiles();
    });

    $('#mte-file-list').on('click', '.mte-item', function (e) {
      e.preventDefault();
      var path = $(this).data('path');
      api('loadFile', { path: path }).then(function (res) {
        state.currentFile = path;
        renderFiles();
        $('#mte-file-body').removeClass('hidden');
        $('#mte-file-title').text(path);
        $('#mte-file-encoding').text(t.encoding + ': ' + res.encoding);
        $('#mte-file-editor').val(res.content);
        $('#mte-file-preview-wrap').addClass('hidden');
        var warn = [];
        if (res.encoding !== 'UTF-8') {
          warn.push(t.converted);
        }
        $('#mte-file-warning').toggleClass('hidden', !warn.length).text(warn.join(' '));
        $('#mte-save-file').prop('disabled', !res.writable);
        renderBackups(res.backups);
      });
    });

    $('#mte-save-file').on('click', function () {
      if (!state.currentFile) {
        return;
      }
      api('saveFile', { path: state.currentFile, content: b64($('#mte-file-editor').val()) }).then(function (res) {
        toast(t.saved);
        renderBackups(res.backups);
      });
    });

    $('#mte-repair-file').on('click', function () {
      api('repair', { content: b64($('#mte-file-editor').val()) }).then(function (res) {
        if (res.changed) {
          $('#mte-file-editor').val(res.content);
          toast(t.repaired);
        } else {
          toast(t.nothingToRepair);
        }
      });
    });

    $('#mte-preview-file').on('click', function () {
      $('#mte-file-preview-wrap').removeClass('hidden');
      var content = $('#mte-file-editor').val();
      if (!/\.html?$/i.test(state.currentFile || '')) {
        content = '<pre style="white-space:pre-wrap;font-family:monospace">' + esc(content) + '</pre>';
      }
      writeFrame(document.getElementById('mte-file-preview'), content);
    });

    $('#mte-backups').on('click', '[data-mte-restore]', function () {
      if (!window.confirm(t.confirmRestore)) {
        return;
      }
      api('restoreBackup', { path: state.currentFile, backup: $(this).data('mte-restore') }).then(function (res) {
        $('#mte-file-editor').val(res.content);
        renderBackups(res.backups);
        toast(t.saved);
      });
    });

    var filesInit = false;
    $('a[href="#mte-tab-files"]').on('shown.bs.tab click', function () {
      if (!filesInit) {
        filesInit = true;
        loadFiles();
      }
    });


    /* ---------------- VISUAL BUILDER (GrapesJS) ---------------- */

    var builder = { editor: null, loading: null, mode: null, layoutId: 0, onSave: null };

    function loadBuilderAssets() {
      if (builder.loading) {
        return builder.loading;
      }
      var d = $.Deferred();
      cfg.builder.css.forEach(function (href) {
        $('<link rel="stylesheet">').attr('href', href).appendTo('head');
      });
      var scripts = cfg.builder.js.slice();
      (function next() {
        if (!scripts.length) {
          d.resolve();
          return;
        }
        var el = document.createElement('script');
        el.src = scripts.shift();
        el.onload = next;
        el.onerror = function () { d.reject(); toast(t.error + ': ' + el.src, true); };
        document.head.appendChild(el);
      })();
      builder.loading = d.promise();
      return builder.loading;
    }

    var tokenLabels = {
      content: t.blockContent,
      logo: t.blockLogo,
      social: t.blockSocial,
      footer: t.blockFooter,
      footer_columns: t.blockColumns
    };

    function tokenBlock(name) {
      return '<div data-mte-token="' + name + '">[[' + name + ']]</div>';
    }

    function setupEditor(container) {
      var preset = window['grapesjs-preset-newsletter'];
      preset = preset && preset.default ? preset.default : preset;
      var messages = {};
      if (window.mteGrapesLocaleEl) {
        messages.el = window.mteGrapesLocaleEl;
      }
      var editor = window.grapesjs.init({
        container: container,
        height: '100%',
        width: 'auto',
        fromElement: false,
        storageManager: false,
        avoidInlineStyle: false,
        plugins: [preset],
        pluginsOpts: {},
        i18n: { locale: cfg.builder.locale, localeFallback: 'en', messages: messages },
        assetManager: { upload: false },
        // The PrestaShop back office already loads Font Awesome: no external request.
        cssIcons: '',
        components: ''
      });

      // Dynamic parts of a design (filled in when the e-mail is sent).
      editor.DomComponents.addType('mte-token', {
        isComponent: function (el) {
          return el && el.getAttribute && el.getAttribute('data-mte-token') ? { type: 'mte-token' } : false;
        },
        model: {
          defaults: { droppable: false, editable: false, stylable: true, highlightable: true, traits: [] }
        },
        view: {
          onRender: function () {
            var name = this.model.getAttributes()['data-mte-token'];
            this.el.setAttribute('data-mte-label', tokenLabels[name] || name);
          }
        }
      });

      var bm = editor.BlockManager;
      var cat = 'PrestaShop';
      bm.add('mte-content', { label: t.blockContent, category: cat, content: tokenBlock('content'), media: '<i class="icon-envelope" style="font-size:28px"></i>' });
      bm.add('mte-logo', { label: t.blockLogo, category: cat, content: tokenBlock('logo'), media: '<i class="icon-picture" style="font-size:28px"></i>' });
      bm.add('mte-social', { label: t.blockSocial, category: cat, content: tokenBlock('social'), media: '<i class="icon-share-alt" style="font-size:28px"></i>' });
      bm.add('mte-footer', { label: t.blockFooter, category: cat, content: tokenBlock('footer'), media: '<i class="icon-align-justify" style="font-size:28px"></i>' });
      bm.add('mte-columns', { label: t.blockColumns, category: cat, content: tokenBlock('footer_columns'), media: '<i class="icon-columns" style="font-size:28px"></i>' });
      bm.add('mte-shop-link', { label: t.blockShopName, category: cat, content: '<p style="text-align:center;font-family:Arial, Helvetica, sans-serif;"><a href="{shop_url}" style="font-weight:bold;">{shop_name}</a></p>', media: '<i class="icon-link" style="font-size:28px"></i>' });
      bm.add('mte-btn', { label: t.insertButton, category: cat, content: '<p style="text-align:center;font-family:Arial, Helvetica, sans-serif;"><a class="mte-button" href="{shop_url}" style="display:inline-block;padding:12px 26px;background-color:#25b9d7;color:#ffffff;border-radius:4px;font-weight:bold;text-decoration:none;">' + esc(t.insertButton) + '</a></p>', media: '<i class="icon-hand-o-up" style="font-size:28px"></i>' });

      if (cfg.builder.locale === 'el') {
        var el = {
          sect100: 'Ενότητα 1 στήλης', sect50: 'Ενότητα 1/2', sect30: 'Ενότητα 1/3', sect37: 'Ενότητα 3/7',
          button: 'Κουμπί', divider: 'Διαχωριστικό', text: 'Κείμενο', 'text-sect': 'Ενότητα κειμένου',
          image: 'Εικόνα', quote: 'Παράθεση', link: 'Σύνδεσμος', 'link-block': 'Μπλοκ συνδέσμου',
          'grid-items': 'Πλέγμα στοιχείων', 'list-items': 'Λίστα στοιχείων'
        };
        $.each(el, function (id, label) {
          var block = bm.get(id);
          if (block) {
            block.set('label', label);
          }
        });
      }

      editor.on('load', function () {
        var doc = editor.Canvas.getDocument();
        var style = doc.createElement('style');
        style.textContent = 'body{font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;}'
          + '[data-mte-token]{outline:2px dashed #25b9d7;outline-offset:-2px;background:rgba(37,185,215,.08);padding:16px 10px;text-align:center;font-size:0 !important;color:transparent;min-height:20px;}'
          + '[data-mte-token]::before{content:attr(data-mte-label);font:600 13px Arial,sans-serif;color:#1d8ca3;}'
          + '[data-mte-token="content"]{padding:60px 10px;}'
          + '.mte-table-var{display:block;}';
        doc.head.appendChild(style);
      });
      return editor;
    }

    function openBuilder(opts) {
      // opts: { mode: 'layout'|'content', html, project, name, layoutId, onSave }
      builder.mode = opts.mode;
      builder.layoutId = opts.layoutId || 0;
      builder.onSave = opts.onSave;
      $('#mte-builder-name').val(opts.name || '').toggle(opts.mode === 'layout');
      $('#mte-builder-preview').toggle(opts.mode === 'layout');
      $('#mte-builder-hint').text(opts.mode === 'layout' ? t.builderHintLayout : t.builderHintContent);
      $('#mte-builder').prop('hidden', false);
      $('body').addClass('mte-builder-open');
      return loadBuilderAssets().then(function () {
        if (!builder.editor) {
          builder.editor = setupEditor('#mte-gjs');
        }
        var ed = builder.editor;
        // Design-only blocks are hidden when editing the content of one e-mail.
        var designOnly = ['mte-content', 'mte-logo', 'mte-social', 'mte-footer', 'mte-columns'];
        ed.BlockManager.render(ed.BlockManager.getAll().filter(function (b) {
          return opts.mode === 'layout' || designOnly.indexOf(b.getId ? b.getId() : b.id) === -1;
        }));
        if (opts.project) {
          try {
            ed.loadProjectData(JSON.parse(opts.project));
            return;
          } catch (e) {}
        }
        // GrapesJS turns inline styles into CSS rules: clear the old rules first.
        ed.setStyle('');
        ed.setComponents(opts.html || '');
      });
    }

    function closeBuilder() {
      $('#mte-builder').prop('hidden', true);
      $('#mte-builder-preview-wrap').prop('hidden', true);
      $('body').removeClass('mte-builder-open');
    }

    function builderHtml() {
      var ed = builder.editor;
      var html = ed.runCommand('gjs-get-inlined-html') || ed.getHtml();
      return html;
    }

    function builderBody() {
      // Content mode: only what is inside <body>.
      var html = builderHtml();
      var m = /<body[^>]*>([\s\S]*)<\/body>/i.exec(html);
      return m ? m[1] : html;
    }

    $('#mte-builder-close').on('click', function () {
      if (window.confirm(t.confirmCloseBuilder)) {
        closeBuilder();
      }
    });

    $('#mte-builder-save').on('click', function () {
      if (!builder.editor) {
        return;
      }
      if (builder.mode === 'content') {
        builder.onSave(builderBody());
        closeBuilder();
        return;
      }
      var name = $.trim($('#mte-builder-name').val());
      if (!name) {
        toast(t.needName, true);
        $('#mte-builder-name').focus();
        return;
      }
      var html = builderHtml();
      if (html.indexOf('[[content]]') === -1) {
        toast(t.needContent, true);
        return;
      }
      api('saveLayout', {
        id_layout: builder.layoutId,
        name: name,
        html: b64(html),
        project: b64(JSON.stringify(builder.editor.getProjectData()))
      }).then(function (res) {
        builder.layoutId = res.id;
        cfg.customLayouts = res.layouts;
        $.each(res.settings, function (key, value) {
          if (!state.settings[key]) {
            state.settings[key] = value;
          }
        });
        readDesignForm();
        state.layout = res.key;
        fillDesignForm();
        refreshDesignPreview();
        toast(t.designSaved);
      });
    });

    $('#mte-builder-preview').on('click', function () {
      var data = previewTemplateParams(designPayload(state.previewLang));
      data.layout = builder.layoutId ? 'custom_' + builder.layoutId : 'custom_0';
      data.settings = JSON.stringify(state.settings[data.layout] || cfg.defaults.custom);
      data.layout_html = b64(builderHtml());
      data.content = '';
      api('preview', data).then(function (res) {
        $('#mte-builder-preview-wrap').prop('hidden', false);
        writeFrame(document.getElementById('mte-builder-preview-frame'), res.html);
      });
    });
    $('#mte-builder-preview-close').on('click', function () {
      $('#mte-builder-preview-wrap').prop('hidden', true);
    });

    function startLayoutBuilder(from, name) {
      api('builderStart', { from: from }).then(function (res) {
        openBuilder({ mode: 'layout', html: res.html, name: name, layoutId: 0 });
      });
    }

    // "New design": choose the starting point (empty, a built-in design or a copy of one of yours).
    function openNewDesign() {
      var $box = $('#mte-start-options').empty();
      var options = [{ key: 'blank', name: t.emptyDesign, thumb: 'custom' }];
      ['prestashop', 'classic', 'modern', 'minimal'].forEach(function (key) {
        options.push({ key: key, name: layoutName(key), thumb: key });
      });
      cfg.customLayouts.forEach(function (l) {
        options.push({ key: l.key, name: l.name, thumb: 'custom' });
      });
      options.forEach(function (o) {
        var $card = $('<div class="mte-layout-card" role="button" tabindex="0">').attr('data-start', o.key);
        $card.append('<span class="mte-thumb mte-thumb-' + o.thumb + '"><i></i><b></b><em></em><em></em><u></u></span>');
        $card.append($('<span class="mte-layout-name">').text(o.name));
        $box.append($card);
      });
      $('#mte-new-design').prop('hidden', false);
    }
    $('#mte-builder-new').on('click', openNewDesign);
    $('#mte-new-design-cancel').on('click', function () {
      $('#mte-new-design').prop('hidden', true);
    });
    $('#mte-start-options').on('click keypress', '[data-start]', function () {
      var from = $(this).data('start');
      $('#mte-new-design').prop('hidden', true);
      if (from === 'blank') {
        startLayoutBuilder('blank', '');
      } else if (isCustom(from)) {
        api('loadLayout', { id_layout: from.substr(7) }).then(function (res) {
          openBuilder({ mode: 'layout', html: res.html, project: res.project, name: res.name + ' (' + t.copy + ')', layoutId: 0 });
        });
      } else {
        startLayoutBuilder(from, layoutName(from) + ' (' + t.copy + ')');
      }
    });
    $('#mte-layouts').on('click', '[data-mte-edit-layout]', function () {
      var key = $(this).data('mte-edit-layout');
      api('loadLayout', { id_layout: key.substr(7) }).then(function (res) {
        openBuilder({ mode: 'layout', html: res.html, project: res.project, name: res.name, layoutId: res.id });
      });
    });
    $('#mte-layouts').on('click', '[data-mte-delete-layout]', function () {
      var key = $(this).data('mte-delete-layout');
      if (!window.confirm(t.confirmDeleteLayout)) {
        return;
      }
      api('deleteLayout', { id_layout: key.substr(7) }).then(function (res) {
        cfg.customLayouts = res.layouts;
        delete state.settings[key];
        if (state.layout === key) {
          state.layout = res.layout;
        }
        fillDesignForm();
        refreshDesignPreview();
        toast(t.saved);
      });
    });

    /* ---------------- VISUAL MODE OF "E-MAIL CONTENTS" ---------------- */

    var visual = { editor: null, loaded: false, ready: false, loading: false, canvas: {}, pending: null, fillNext: null };

    var lockedProps = {
      selectable: false, hoverable: false, editable: false, draggable: false, droppable: false,
      removable: false, copyable: false, badgable: false, layerable: false, highlightable: false
    };

    function isInside(component, ancestor) {
      for (var c = component; c; c = c.parent()) {
        if (c === ancestor) {
          return true;
        }
      }
      return false;
    }

    function visualRegion() {
      return visual.editor ? visual.editor.getWrapper().find('[data-mte-region]')[0] : null;
    }

    function visualCanvas(idLang) {
      if (visual.canvas[idLang]) {
        return $.Deferred().resolve(visual.canvas[idLang]).promise();
      }
      return api('contentCanvas', { id_lang: idLang }).then(function (res) {
        visual.canvas[idLang] = res;
        return res;
      });
    }

    function visualStyles(canvas) {
      var doc = visual.editor.Canvas.getDocument();
      var el = doc.getElementById('mte-design-css');
      if (!el) {
        el = doc.createElement('style');
        el.id = 'mte-design-css';
        doc.head.appendChild(el);
      }
      el.textContent = canvas.css
        + 'body{' + (canvas.bodyStyle || '') + '}'
        + '[data-mte-region]{outline:2px dashed rgba(37,185,215,.7);outline-offset:6px;min-height:60px;}'
        + '[data-mte-region]:empty::before{content:"' + t.blockContent.replace(/"/g, '') + '";display:block;padding:20px;text-align:center;color:#25b9d7;}';
    }

    function visualLoad(html) {
      visual.ready = false;
      visual.pending = html;
      var idLang = state.contentLang;
      $.when(loadBuilderAssets(), visualCanvas(idLang)).then(function (x, canvas) {
        if (visual.pending !== html || state.mode !== 'visual') {
          return;
        }
        var first = !visual.editor;
        if (first) {
          visual.editor = setupEditor('#mte-visual-gjs');
          // Design-only blocks make no sense inside the content of one e-mail.
          ['mte-content', 'mte-logo', 'mte-social', 'mte-footer', 'mte-columns'].forEach(function (id) {
            visual.editor.BlockManager.remove(id);
          });
          visual.editor.on('load', function () {
            visual.loaded = true;
            if (visual.fillNext) {
              visual.fillNext();
            }
          });
          visual.editor.on('component:add component:remove component:update', function () {
            if (visual.ready && !visual.loading) {
              state.dirty = true;
            }
          });
        }
        var ed = visual.editor;
        var fill = function () {
          visual.loading = true;
          ed.setStyle('');
          ed.setComponents(canvas.html.split('[[mte_region]]').join(html || ''));
          var region = visualRegion();
          var wrapper = ed.getWrapper();
          wrapper.set({ droppable: false });
          wrapper.find('*').forEach(function (c) {
            if (!region || !isInside(c, region)) {
              c.set(lockedProps);
            }
          });
          if (region) {
            region.set({ removable: false, draggable: false, copyable: false, droppable: true, selectable: true, hoverable: true, layerable: true });
          }
          visualStyles(canvas);
          visual.fillNext = null;
          visual.ready = true;
          setTimeout(function () { visual.loading = false; }, 300);
        };
        visual.fillNext = fill;
        if (visual.loaded) {
          fill();
        }
      });
    }

    function visualGetContent() {
      var ed = visual.editor;
      var html = ed.runCommand('gjs-get-inlined-html') || ed.getHtml();
      var doc = new window.DOMParser().parseFromString(html, 'text/html');
      var region = doc.querySelector('[data-mte-region]');
      var out = region ? region.innerHTML : '';
      // Ids added by the builder for its own styling.
      return out.replace(/\sid="i[a-z0-9]{2,8}(-\d+)*"/g, '');
    }

    function visualInsert(html) {
      var ed = visual.editor;
      var region = visualRegion();
      if (ed.getEditing && ed.getEditing()) {
        ed.Canvas.getDocument().execCommand('insertHTML', false, html);
        return;
      }
      var sel = ed.getSelected();
      if (sel && sel !== region && region && isInside(sel, region) && sel.parent()) {
        sel.parent().append(html, { at: sel.index() + 1 });
      } else if (region) {
        region.append(html);
      }
    }

    // A new design or language changes the canvas.
    $('#mte-save-design').on('click', function () {
      visual.canvas = {};
    });

    if (!cfg.canEdit) {
      $('#mte-save-design, #mte-save-template, #mte-save-file, #mte-delete-template, #mte-bulk-import, #mte-builder-save').prop('disabled', true);
    }
  });
})(jQuery);
