<?php defined('\ABSPATH') || exit; ?>

<div class="wrap">
    <div class="cegg5-container" id="cegg-feed-wizard" style="max-width: 1140px;">

        <div class="cfw-header mt-4 mb-4">
            <div>
                <h2 class="h4 mb-1"><?php esc_html_e('Add a Feed', 'content-egg'); ?></h2>
                <p class="cfw-subtitle" id="cfw-subtitle"><?php esc_html_e('Paste a product feed URL below — the plugin will try to detect the format, fields and currency automatically.', 'content-egg'); ?></p>
            </div>
            <a class="cfw-manual-link" href="<?php echo esc_url(remove_query_arg('wizard')); ?>">
                <?php esc_html_e('Set up manually', 'content-egg'); ?>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <!-- Step indicator -->
        <div class="cfw-stepper mb-4" id="cfw-steps">
            <div class="cfw-step is-active" data-step="1">
                <span class="cfw-step-circle">1</span>
                <span class="cfw-step-label"><?php esc_html_e('Feed URL', 'content-egg'); ?></span>
            </div>
            <div class="cfw-step-line"></div>
            <div class="cfw-step is-upcoming" data-step="2">
                <span class="cfw-step-circle">2</span>
                <span class="cfw-step-label"><?php esc_html_e('Field mapping', 'content-egg'); ?></span>
            </div>
            <div class="cfw-step-line"></div>
            <div class="cfw-step is-upcoming" data-step="3">
                <span class="cfw-step-circle">3</span>
                <span class="cfw-step-label"><?php esc_html_e('Confirm & import', 'content-egg'); ?></span>
            </div>
        </div>

        <!-- Step 1: URL -->
        <div id="cfw-step-1">
            <div class="cfw-panel">
                <div class="cfw-panel-body">
                    <label for="cfw-url" class="form-label fw-bold"><?php esc_html_e('Feed URL', 'content-egg'); ?></label>
                    <div class="input-group">
                        <input type="url" class="form-control" id="cfw-url"
                            placeholder="https://example.com/products.csv"
                            aria-describedby="cfw-url-help" />
                        <button class="btn btn-primary" type="button" id="cfw-analyze" disabled>
                            <i class="bi bi-search me-1" aria-hidden="true"></i><?php esc_html_e('Analyze feed', 'content-egg'); ?>
                        </button>
                    </div>
                    <div id="cfw-url-help" class="form-text">
                        <?php esc_html_e('CSV, XML or JSON product feed. ZIP and GZIP archives are supported. The plugin will detect the format and settings automatically.', 'content-egg'); ?>
                    </div>
                    <div id="cfw-url-error" class="text-danger small mt-1 d-none">
                        <?php esc_html_e('Please enter a valid feed URL (starting with http://, https://, ftp://, or ftps://).', 'content-egg'); ?>
                    </div>
                    <div id="cfw-analyze-progress" class="mt-3 d-none">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="small text-muted"><?php esc_html_e('Downloading a sample and detecting settings…', 'content-egg'); ?></span>
                    </div>
                    <div id="cfw-analyze-error" class="alert alert-warning mt-3 d-none"></div>
                </div>
            </div>
        </div>

        <!-- Step 2: Mapping -->
        <div id="cfw-step-2" class="d-none">
            <div id="cfw-detected" class="mb-3"></div>

            <!-- Product node override (XML feeds only) -->
            <div class="cfw-panel mb-3 d-none" id="cfw-node-row">
                <div class="cfw-panel-body d-flex align-items-center flex-wrap gap-2">
                    <label for="cfw-node" class="fw-bold mb-0 me-1"><?php esc_html_e('Product node', 'content-egg'); ?></label>
                    <input type="text" class="form-control form-control-sm" id="cfw-node" style="max-width: 220px;" />
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cfw-node-rescan">
                        <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><?php esc_html_e('Re-scan fields', 'content-egg'); ?>
                    </button>
                    <span class="small text-muted">
                        <?php esc_html_e('The XML element that repeats once per product. Change it if the sample below looks wrong, then re-scan.', 'content-egg'); ?>
                    </span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="cfw-panel h-100">
                        <div class="cfw-panel-header">
                            <span><?php esc_html_e('Field mapping', 'content-egg'); ?></span>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle text-muted d-none" id="cfw-ai-map-info" aria-hidden="true"></i>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="cfw-ai-map">
                                    <i class="bi bi-stars me-1" aria-hidden="true"></i><?php esc_html_e('Map with AI', 'content-egg'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="cfw-panel-body" id="cfw-mapping"></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="cfw-panel h-100">
                        <div class="cfw-panel-header"><?php esc_html_e('Sample data from your feed', 'content-egg'); ?></div>
                        <div class="cfw-panel-body cfw-panel-body-flush" style="max-height: 520px; overflow: auto;">
                            <table class="table table-sm table-striped mb-0 small" id="cfw-sample"></table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cfw-panel mt-3" id="cfw-preview-panel">
                <div class="cfw-panel-header"><?php esc_html_e('Product preview (first row, using your mapping)', 'content-egg'); ?></div>
                <div class="cfw-panel-body" id="cfw-preview"></div>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <button type="button" class="btn btn-outline-secondary" id="cfw-back-1"><?php esc_html_e('Back', 'content-egg'); ?></button>
                <div class="d-flex align-items-center">
                    <span id="cfw-mapping-error" class="text-danger small me-3 d-none"></span>
                    <button type="button" class="btn btn-primary" id="cfw-continue-2"><?php esc_html_e('Continue', 'content-egg'); ?></button>
                </div>
            </div>
        </div>

        <!-- Step 3: Confirm & import -->
        <div id="cfw-step-3" class="d-none">
            <div class="cfw-panel">
                <div class="cfw-panel-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="cfw-name" class="form-label fw-bold"><?php esc_html_e('Feed name', 'content-egg'); ?></label>
                            <input type="text" class="form-control" id="cfw-name" />

                            <label for="cfw-interval" class="form-label fw-bold mt-3"><?php esc_html_e('Feed sync interval', 'content-egg'); ?></label>
                            <select class="form-select" id="cfw-interval">
                                <option value="3600."><?php esc_html_e('Every 1 hour', 'content-egg'); ?></option>
                                <option value="10800."><?php esc_html_e('Every 3 hours', 'content-egg'); ?></option>
                                <option value="21600."><?php esc_html_e('Every 6 hours', 'content-egg'); ?></option>
                                <option value="43200." selected><?php esc_html_e('Every 12 hours (default)', 'content-egg'); ?></option>
                                <option value="86400."><?php esc_html_e('Every 1 day', 'content-egg'); ?></option>
                                <option value="259200."><?php esc_html_e('Every 3 days', 'content-egg'); ?></option>
                                <option value="604800."><?php esc_html_e('Every 1 week', 'content-egg'); ?></option>
                            </select>

                            <div class="mt-3">
                                <label for="cfw-instock">
                                    <input type="checkbox" id="cfw-instock" checked />
                                    <?php esc_html_e('Only import in-stock products', 'content-egg'); ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-bold mb-2"><?php esc_html_e('Summary', 'content-egg'); ?></div>
                            <ul class="list-unstyled small" id="cfw-summary"></ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-3" id="cfw-finish-row">
                <button type="button" class="btn btn-outline-secondary" id="cfw-back-2"><?php esc_html_e('Back', 'content-egg'); ?></button>
                <button type="button" class="btn btn-success" id="cfw-finish">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?php esc_html_e('Save & import products', 'content-egg'); ?>
                </button>
            </div>

            <div class="cfw-panel mt-3 d-none" id="cfw-progress">
                <div class="cfw-panel-body text-center py-4">
                    <div id="cfw-progress-running">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <div class="fw-bold" id="cfw-progress-label"><?php esc_html_e('Importing products…', 'content-egg'); ?></div>
                        <div class="cfw-progress-track mx-auto mt-3 d-none" id="cfw-progress-track" style="max-width: 320px;">
                            <div class="cfw-progress-fill" id="cfw-progress-fill"></div>
                        </div>
                        <div class="text-muted small mt-2 d-none" id="cfw-progress-pct"></div>
                        <div class="text-muted small mt-1" id="cfw-progress-rows"></div>
                    </div>
                    <div id="cfw-progress-done" class="d-none">
                        <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;" aria-hidden="true"></i>
                        <div class="fw-bold mt-2" id="cfw-done-label"></div>
                        <div class="mt-3">
                            <a href="#" class="btn btn-primary me-2" id="cfw-done-settings"><?php esc_html_e('Module settings', 'content-egg'); ?></a>
                            <a href="#" class="btn btn-outline-primary" id="cfw-done-post"><?php esc_html_e('Create a post', 'content-egg'); ?></a>
                        </div>
                    </div>
                    <div id="cfw-progress-failed" class="d-none">
                        <i class="bi bi-x-circle text-danger" style="font-size: 2.5rem;" aria-hidden="true"></i>
                        <div class="fw-bold mt-2 text-danger" id="cfw-failed-label"></div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-secondary" id="cfw-failed-back"><?php esc_html_e('Back to mapping', 'content-egg'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
