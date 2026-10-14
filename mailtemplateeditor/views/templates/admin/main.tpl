{*
 * Mail Template Editor - back-office page
 *}
<div id="mte-app" class="mte-app">
  <script type="application/json" id="mte-config">{$mte_config_json nofilter}</script>

  <ul class="nav nav-tabs mte-tabs" role="tablist">
    <li class="active"><a href="#mte-tab-design" data-toggle="tab"><i class="icon-paint-brush"></i> {l s='Design' mod='mailtemplateeditor'}</a></li>
    <li><a href="#mte-tab-content" data-toggle="tab"><i class="icon-envelope"></i> {l s='E-mail contents' mod='mailtemplateeditor'}</a></li>
    <li><a href="#mte-tab-files" data-toggle="tab"><i class="icon-file-code-o"></i> {l s='Template files' mod='mailtemplateeditor'}</a></li>
  </ul>

  <div class="tab-content">

    {* ============================== DESIGN ============================== *}
    <div class="tab-pane active" id="mte-tab-design">
      <div class="row">
        <div class="col-lg-5 mte-design-form">
          <div class="panel">
            <div class="panel-heading"><i class="icon-cogs"></i> {l s='Status' mod='mailtemplateeditor'}</div>
            <div class="form-group">
              <label>{l s='Apply the design to' mod='mailtemplateeditor'}</label>
              <select class="form-control" data-mte-global="mode">
                <option value="0">{l s='Nothing (module disabled, original e-mails are sent)' mod='mailtemplateeditor'}</option>
                <option value="1">{l s='Only the e-mails I have customised' mod='mailtemplateeditor'}</option>
                <option value="2">{l s='All e-mails (non-customised ones use their original content)' mod='mailtemplateeditor'}</option>
              </select>
              <p class="help-block">{l s='Tip: start with "Only the e-mails I have customised" and use "Import all originals" in the E-mail contents tab.' mod='mailtemplateeditor'}</p>
            </div>
          </div>

          <div class="panel">
            <div class="panel-heading"><i class="icon-th-large"></i> {l s='Design' mod='mailtemplateeditor'}</div>
            <div class="mte-layouts" id="mte-layouts"></div>
            <div class="mte-layout-actions">
              <button type="button" class="btn btn-default btn-sm" id="mte-builder-new"><i class="icon-plus"></i> {l s='New design' mod='mailtemplateeditor'}</button>
            </div>
            <p class="help-block">{l s='Each design keeps its own colours and sizes. Switching design does not lose your changes.' mod='mailtemplateeditor'}</p>
          </div>

          <div class="panel">
            <div class="panel-heading"><i class="icon-tint"></i> {l s='Colours' mod='mailtemplateeditor'}</div>
            <div class="mte-colors">
              <div class="mte-color"><input type="color" data-mte-setting="page_bg" id="mte-c-page_bg"><label for="mte-c-page_bg">{l s='Page background' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="header_bg" id="mte-c-header_bg"><label for="mte-c-header_bg">{l s='Header background' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="header_text" id="mte-c-header_text"><label for="mte-c-header_text">{l s='Header text' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="accent" id="mte-c-accent"><label for="mte-c-accent">{l s='Accent / lines' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="content_bg" id="mte-c-content_bg"><label for="mte-c-content_bg">{l s='Content background' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="text_color" id="mte-c-text_color"><label for="mte-c-text_color">{l s='Text' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="heading_color" id="mte-c-heading_color"><label for="mte-c-heading_color">{l s='Headings' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="link_color" id="mte-c-link_color"><label for="mte-c-link_color">{l s='Links' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="button_bg" id="mte-c-button_bg"><label for="mte-c-button_bg">{l s='Button background' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="button_text" id="mte-c-button_text"><label for="mte-c-button_text">{l s='Button text' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="border_color" id="mte-c-border_color"><label for="mte-c-border_color">{l s='Borders' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="footer_bg" id="mte-c-footer_bg"><label for="mte-c-footer_bg">{l s='Footer background' mod='mailtemplateeditor'}</label></div>
              <div class="mte-color"><input type="color" data-mte-setting="footer_text" id="mte-c-footer_text"><label for="mte-c-footer_text">{l s='Footer text' mod='mailtemplateeditor'}</label></div>
            </div>
          </div>

          <div class="panel">
            <div class="panel-heading"><i class="icon-font"></i> {l s='Fonts & sizes' mod='mailtemplateeditor'}</div>
            <div class="form-group">
              <label>{l s='Font' mod='mailtemplateeditor'}</label>
              <select class="form-control" data-mte-setting="font_family" id="mte-font-select"></select>
            </div>
            <div class="row">
              <div class="col-xs-6 col-sm-3 form-group"><label>{l s='Text (px)' mod='mailtemplateeditor'}</label><input type="number" class="form-control" min="10" max="24" data-mte-setting="font_size"></div>
              <div class="col-xs-6 col-sm-3 form-group"><label>{l s='Headings (px)' mod='mailtemplateeditor'}</label><input type="number" class="form-control" min="14" max="48" data-mte-setting="heading_size"></div>
              <div class="col-xs-6 col-sm-3 form-group"><label>{l s='Footer (px)' mod='mailtemplateeditor'}</label><input type="number" class="form-control" min="9" max="18" data-mte-setting="small_size"></div>
              <div class="col-xs-6 col-sm-3 form-group"><label>{l s='Line height' mod='mailtemplateeditor'}</label><input type="number" class="form-control" min="1" max="2.5" step="0.1" data-mte-setting="line_height"></div>
            </div>
            <div class="checkbox"><label><input type="checkbox" data-mte-setting="uppercase_headings"> {l s='Uppercase headings' mod='mailtemplateeditor'}</label></div>
          </div>

          <div class="panel">
            <div class="panel-heading"><i class="icon-columns"></i> {l s='Layout' mod='mailtemplateeditor'}</div>
            <div class="row">
              <div class="col-xs-6 form-group"><label>{l s='E-mail width (px)' mod='mailtemplateeditor'}</label><input type="number" class="form-control" min="400" max="900" step="10" data-mte-setting="width"></div>
              <div class="col-xs-6 form-group"><label>{l s='Rounded corners (px)' mod='mailtemplateeditor'}</label><input type="number" class="form-control" min="0" max="30" data-mte-setting="radius"></div>
            </div>
            <div class="checkbox"><label><input type="checkbox" data-mte-setting="show_logo"> {l s='Show the shop logo' mod='mailtemplateeditor'}</label></div>
            <div class="checkbox"><label><input type="checkbox" data-mte-setting="show_shop_name"> {l s='Show the shop name under the logo' mod='mailtemplateeditor'}</label></div>
            <div class="row">
              <div class="col-xs-6 form-group"><label>{l s='Logo width (px)' mod='mailtemplateeditor'}</label><input type="number" class="form-control" min="40" max="600" data-mte-setting="logo_width"></div>
              <div class="col-xs-6 form-group"><label>{l s='Logo position' mod='mailtemplateeditor'}</label>
                <select class="form-control" data-mte-setting="logo_align">
                  <option value="left">{l s='Left' mod='mailtemplateeditor'}</option>
                  <option value="center">{l s='Center' mod='mailtemplateeditor'}</option>
                  <option value="right">{l s='Right' mod='mailtemplateeditor'}</option>
                </select>
              </div>
            </div>
            <p class="help-block mte-no-logo hidden"><i class="icon-warning"></i> {l s='No e-mail logo found: the shop name is shown instead. Set it in Design > Theme & Logo.' mod='mailtemplateeditor'}</p>
          </div>

          <div class="panel">
            <div class="panel-heading"><i class="icon-align-justify"></i> {l s='Footer' mod='mailtemplateeditor'}
              <select class="form-control input-sm mte-inline-select" id="mte-footer-lang"></select>
            </div>
            <div class="form-group">
              <label>{l s='About us (HTML allowed)' mod='mailtemplateeditor'}</label>
              <textarea class="form-control" rows="3" data-mte-ml="about"></textarea>
            </div>
            <div class="form-group">
              <label>{l s='Contact information (HTML allowed)' mod='mailtemplateeditor'}</label>
              <textarea class="form-control" rows="3" data-mte-ml="contact"></textarea>
            </div>
            <div class="form-group">
              <label>{l s='Footer text (HTML allowed)' mod='mailtemplateeditor'}</label>
              <textarea class="form-control" rows="2" data-mte-ml="footer"></textarea>
            </div>
            <label>{l s='Social networks (full URL, leave empty to hide)' mod='mailtemplateeditor'}</label>
            <div id="mte-social"></div>
          </div>

          <div class="panel">
            <div class="panel-heading"><i class="icon-ban"></i> {l s='Exclusions' mod='mailtemplateeditor'}</div>
            <div class="form-group">
              <label>{l s='Never apply the design to these e-mails (one per line, e.g. "order_conf" or "ps_emailalerts/new_order")' mod='mailtemplateeditor'}</label>
              <textarea class="form-control" rows="3" data-mte-global="skip"></textarea>
            </div>
          </div>

          <div class="panel mte-sticky-actions">
            <button type="button" class="btn btn-primary" id="mte-save-design"><i class="icon-save"></i> {l s='Save design' mod='mailtemplateeditor'}</button>
            <button type="button" class="btn btn-default" id="mte-reset-design"><i class="icon-undo"></i> {l s='Reset this design to defaults' mod='mailtemplateeditor'}</button>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="panel mte-preview-panel">
            <div class="panel-heading"><i class="icon-eye"></i> {l s='Live preview' mod='mailtemplateeditor'}
              <span class="mte-preview-tools">
                <select class="form-control input-sm mte-inline-select" id="mte-preview-template" title="{l s='E-mail shown in the preview' mod='mailtemplateeditor'}"></select>
                <select class="form-control input-sm mte-inline-select" id="mte-preview-lang"></select>
                <span class="mte-seg mte-seg-inline" role="group">
                  <button type="button" class="mte-seg-btn active" data-mte-device="desktop" title="Desktop"><i class="icon-desktop"></i></button>
                  <button type="button" class="mte-seg-btn" data-mte-device="mobile" title="Mobile"><i class="icon-mobile"></i></button>
                </span>
              </span>
            </div>
            <div class="mte-frame-wrap"><iframe id="mte-design-preview" class="mte-frame" title="preview"></iframe></div>
          </div>
        </div>
      </div>
    </div>

    {* ============================== CONTENTS ============================== *}
    <div class="tab-pane" id="mte-tab-content">
      <div class="row">
        <div class="col-md-4 col-lg-3">
          <div class="panel mte-list-panel">
            <div class="panel-heading"><i class="icon-list"></i> {l s='E-mails' mod='mailtemplateeditor'}</div>
            <select class="form-control" id="mte-content-lang"></select>
            <input type="search" class="form-control" id="mte-template-search" placeholder="{l s='Search...' mod='mailtemplateeditor'}">
            <div class="mte-seg mte-filter" role="group">
              <button type="button" class="mte-seg-btn active" data-mte-filter="all">{l s='All' mod='mailtemplateeditor'}</button>
              <button type="button" class="mte-seg-btn" data-mte-filter="custom">{l s='Customised' mod='mailtemplateeditor'}</button>
            </div>
            <div id="mte-template-list" class="mte-list"></div>
            <button type="button" class="btn btn-default btn-block" id="mte-bulk-import"><i class="icon-download"></i> {l s='Import all originals' mod='mailtemplateeditor'}</button>
            <p class="help-block">{l s='Copies the content of every e-mail not yet customised into the module, ready to edit in the new design.' mod='mailtemplateeditor'}</p>
          </div>
        </div>

        <div class="col-md-8 col-lg-9">
          <div class="panel" id="mte-editor-panel">
            <div class="panel-heading">
              <i class="icon-pencil"></i> <span id="mte-editing-title">{l s='Select an e-mail from the list.' mod='mailtemplateeditor'}</span>
              <small class="text-muted" id="mte-editing-file"></small>
            </div>
            <div id="mte-editor-body" class="hidden">
              <div class="row">
                <div class="col-sm-9 form-group">
                  <label>{l s='Subject (optional, empty = PrestaShop default subject; variables allowed)' mod='mailtemplateeditor'}</label>
                  <input type="text" class="form-control" id="mte-subject">
                </div>
                <div class="col-sm-3 form-group">
                  <label>&nbsp;</label>
                  <div class="checkbox"><label><input type="checkbox" id="mte-active" checked> {l s='Active' mod='mailtemplateeditor'}</label></div>
                </div>
              </div>

              <div class="mte-toolbar">
                <button type="button" class="btn btn-default btn-sm" id="mte-import"><i class="icon-download"></i> {l s='Import original content' mod='mailtemplateeditor'}</button>
                <label class="mte-small-check"><input type="checkbox" id="mte-normalize" checked> {l s='remove old fonts/colours' mod='mailtemplateeditor'}</label>
                <span class="mte-sep"></span>
                <button type="button" class="btn btn-default btn-sm" id="mte-insert-button"><i class="icon-hand-o-up"></i> {l s='Button' mod='mailtemplateeditor'}</button>
                <button type="button" class="btn btn-default btn-sm" id="mte-insert-box"><i class="icon-square-o"></i> {l s='Box' mod='mailtemplateeditor'}</button>
                <button type="button" class="btn btn-default btn-sm" id="mte-repair" title="{l s='Fixes broken text such as ÎšÎ±Î»Î·Î¼Î­ÏÎ± back into Greek' mod='mailtemplateeditor'}"><i class="icon-magic"></i> {l s='Repair Greek characters' mod='mailtemplateeditor'}</button>
                <span class="mte-seg mte-seg-inline mte-mode-switch" role="group">
                  <button type="button" class="mte-seg-btn" data-mte-mode="visual" title="{l s='Edit inside the design, as the customer will see it' mod='mailtemplateeditor'}"><i class="icon-th-large"></i> {l s='Visual' mod='mailtemplateeditor'}</button>
                  <button type="button" class="mte-seg-btn" data-mte-mode="text"><i class="icon-font"></i> {l s='Text' mod='mailtemplateeditor'}</button>
                  <button type="button" class="mte-seg-btn" data-mte-mode="html"><i class="icon-code"></i> HTML</button>
                </span>
              </div>

              <div id="mte-visual" class="mte-visual" hidden>
                <p class="mte-visual-hint"><i class="icon-info-circle"></i> {l s='Only the area with the dashed frame is the content of this e-mail; the rest is the design. Drag blocks from the right, click a text to edit it.' mod='mailtemplateeditor'}</p>
                <div class="mte-visual-editor"><div id="mte-visual-gjs"></div></div>
              </div>
              <textarea id="mte-editor" class="form-control mte-code" rows="22"></textarea>

              <div class="mte-vars">
                <strong>{l s='Variables of this e-mail (click to insert)' mod='mailtemplateeditor'}:</strong>
                <span id="mte-vars"></span>
              </div>

              <div class="mte-actions">
                <button type="button" class="btn btn-primary" id="mte-save-template"><i class="icon-save"></i> {l s='Save' mod='mailtemplateeditor'}</button>
                <button type="button" class="btn btn-default" id="mte-preview-template"><i class="icon-eye"></i> {l s='Preview' mod='mailtemplateeditor'}</button>
                <span class="mte-test">
                  <input type="email" class="form-control" id="mte-test-email">
                  <button type="button" class="btn btn-default" id="mte-send-test"><i class="icon-send"></i> {l s='Send test' mod='mailtemplateeditor'}</button>
                </span>
                <button type="button" class="btn btn-danger pull-right" id="mte-delete-template"><i class="icon-trash"></i> {l s='Delete customisation' mod='mailtemplateeditor'}</button>
              </div>
              <p class="help-block">{l s='The test e-mail is sent through PrestaShop with sample values, using the saved version.' mod='mailtemplateeditor'}</p>

              <div class="mte-frame-wrap hidden" id="mte-content-preview-wrap"><iframe id="mte-content-preview" class="mte-frame" title="preview"></iframe></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {* ============================== FILES ============================== *}
    <div class="tab-pane" id="mte-tab-files">
      <div class="alert alert-info">
        {l s='Direct editing of the original template files (HTML, TXT, Twig, CSS). Files are always saved as UTF-8 and a backup is kept before each save. You normally do not need this tab: use "E-mail contents" instead.' mod='mailtemplateeditor'}
      </div>
      <div class="row">
        <div class="col-md-4 col-lg-3">
          <div class="panel mte-list-panel">
            <div class="panel-heading"><i class="icon-folder-open"></i> {l s='Files' mod='mailtemplateeditor'}</div>
            <select class="form-control" id="mte-files-lang"></select>
            <input type="search" class="form-control" id="mte-file-search" placeholder="{l s='Search...' mod='mailtemplateeditor'}">
            <div id="mte-file-list" class="mte-list"></div>
          </div>
        </div>
        <div class="col-md-8 col-lg-9">
          <div class="panel">
            <div class="panel-heading"><i class="icon-file-text"></i> <span id="mte-file-title">{l s='Select a file from the list.' mod='mailtemplateeditor'}</span>
              <span class="label label-default" id="mte-file-encoding"></span>
            </div>
            <div id="mte-file-body" class="hidden">
              <div id="mte-file-warning" class="alert alert-warning hidden"></div>
              <textarea id="mte-file-editor" class="form-control mte-code" rows="26" spellcheck="false"></textarea>
              <div class="mte-actions">
                <button type="button" class="btn btn-primary" id="mte-save-file"><i class="icon-save"></i> {l s='Save file' mod='mailtemplateeditor'}</button>
                <button type="button" class="btn btn-default" id="mte-repair-file"><i class="icon-magic"></i> {l s='Repair Greek characters' mod='mailtemplateeditor'}</button>
                <button type="button" class="btn btn-default" id="mte-preview-file"><i class="icon-eye"></i> {l s='Preview' mod='mailtemplateeditor'}</button>
              </div>
              <div class="mte-frame-wrap hidden" id="mte-file-preview-wrap"><iframe id="mte-file-preview" class="mte-frame" title="preview"></iframe></div>
              <h4>{l s='Backups' mod='mailtemplateeditor'}</h4>
              <table class="table table-condensed"><tbody id="mte-backups"></tbody></table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="mte-toast" class="mte-toast"></div>

  {* ============================== NEW DESIGN ============================== *}
  <div id="mte-new-design" class="mte-modal" hidden>
    <div class="mte-modal-box" role="dialog" aria-labelledby="mte-new-design-title">
      <h3 id="mte-new-design-title"><i class="icon-plus"></i> {l s='New design' mod='mailtemplateeditor'}</h3>
      <p>{l s='Start from:' mod='mailtemplateeditor'}</p>
      <div class="mte-layouts" id="mte-start-options"></div>
      <div class="mte-modal-actions">
        <button type="button" class="btn btn-default" id="mte-new-design-cancel">{l s='Cancel' mod='mailtemplateeditor'}</button>
      </div>
    </div>
  </div>

  {* ============================== VISUAL BUILDER ============================== *}
  <div id="mte-builder" class="mte-builder" hidden>
    <div class="mte-builder-bar">
      <strong class="mte-builder-title"><i class="icon-th-large"></i> {l s='Visual builder' mod='mailtemplateeditor'}</strong>
      <input type="text" class="form-control mte-builder-name" id="mte-builder-name" placeholder="{l s='Design name' mod='mailtemplateeditor'}">
      <span class="mte-builder-hint" id="mte-builder-hint"></span>
      <span class="mte-builder-buttons">
        <button type="button" class="btn btn-default" id="mte-builder-preview"><i class="icon-eye"></i> {l s='Preview with e-mail' mod='mailtemplateeditor'}</button>
        <button type="button" class="btn btn-primary" id="mte-builder-save"><i class="icon-save"></i> {l s='Save' mod='mailtemplateeditor'}</button>
        <button type="button" class="btn btn-default" id="mte-builder-close"><i class="icon-remove"></i> {l s='Close' mod='mailtemplateeditor'}</button>
      </span>
    </div>
    <div class="mte-builder-body"><div id="mte-gjs"></div></div>
    <div class="mte-builder-preview" id="mte-builder-preview-wrap" hidden>
      <div class="mte-builder-preview-bar">
        <strong>{l s='Preview with e-mail' mod='mailtemplateeditor'}</strong>
        <button type="button" class="btn btn-default btn-sm" id="mte-builder-preview-close"><i class="icon-remove"></i> {l s='Close' mod='mailtemplateeditor'}</button>
      </div>
      <iframe id="mte-builder-preview-frame" class="mte-frame" title="preview"></iframe>
    </div>
  </div>
</div>
