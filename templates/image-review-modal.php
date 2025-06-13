<?php
/**
 * Template for image review modal - Modern Dark UI
 */
?>
<div id="cm-image-review-modal" class="modal">
    <div class="modal-card" style="max-width: 1000px;">
        <header class="modal-card-head">
            <h3 class="modal-card-title">🔍 <?php _e('Image Review', 'catalog-master'); ?></h3>
            <button class="delete" aria-label="close"></button>
        </header>
        
        <section class="modal-card-body">
            <div class="menu-master-grid grid-2">
                <!-- Current Image -->
                <div class="menu-master-card">
                    <h4 class="text-primary mb-3">📷 <?php _e('Current Image', 'catalog-master'); ?></h4>
                    <div class="image-comparison-container">
                        <div class="image-preview-wrapper" id="cm-local-image">
                            <!-- Current image will be inserted here -->
                        </div>
                        <div class="image-info-panel" id="cm-local-info">
                            <!-- Current image info will be inserted here -->
                        </div>
                    </div>
                </div>
                
                <!-- New Image -->
                <div class="menu-master-card">
                    <h4 class="text-success mb-3">✨ <?php _e('New Image', 'catalog-master'); ?></h4>
                    <div class="image-comparison-container">
                        <div class="image-preview-wrapper" id="cm-remote-image">
                            <!-- New image will be inserted here -->
                        </div>
                        <div class="image-info-panel" id="cm-remote-info">
                            <!-- New image info will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Comparison Stats -->
            <div class="comparison-stats mt-4">
                <div class="menu-master-grid grid-3">
                    <div class="stats-card">
                        <div class="stats-number" id="size-comparison">-</div>
                        <div class="stats-label">Size Difference</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number" id="quality-comparison">-</div>
                        <div class="stats-label">Quality</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number" id="format-comparison">-</div>
                        <div class="stats-label">Format</div>
                    </div>
                </div>
            </div>
        </section>
        
        <footer class="modal-card-foot">
            <button class="btn btn-success" id="cm-keep-remote">
                ✅ <?php _e('Use New Image', 'catalog-master'); ?>
            </button>
            <button class="btn btn-secondary" id="cm-keep-local">
                📷 <?php _e('Keep Current', 'catalog-master'); ?>
            </button>
            <button class="btn btn-danger" id="cm-skip">
                ⏭️ <?php _e('Skip', 'catalog-master'); ?>
            </button>
        </footer>
    </div>
</div>

<style>
.image-comparison-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.image-preview-wrapper {
    background: var(--mm-bg-elevated);
    border-radius: var(--mm-radius-lg);
    padding: 1rem;
    border: 1px solid var(--mm-border);
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-preview-wrapper img {
    max-width: 100%;
    max-height: 180px;
    object-fit: contain;
    border-radius: var(--mm-radius);
    box-shadow: var(--mm-shadow);
}

.image-info-panel {
    background: var(--mm-bg-elevated);
    border-radius: var(--mm-radius);
    padding: 1rem;
    border: 1px solid var(--mm-border);
    font-size: 0.875rem;
}

.image-info-panel .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--mm-border);
}

.image-info-panel .info-item:last-child {
    border-bottom: none;
}

.image-info-panel .info-label {
    color: var(--mm-text-secondary);
    font-weight: 500;
}

.image-info-panel .info-value {
    color: var(--mm-text-primary);
    font-weight: 600;
}

.comparison-stats {
    background: var(--mm-bg-elevated);
    border-radius: var(--mm-radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--mm-border);
}

.comparison-stats .stats-card {
    background: var(--mm-bg-card);
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .image-comparison-container {
        gap: 0.5rem;
    }
    
    .image-preview-wrapper {
        min-height: 150px;
    }
    
    .image-preview-wrapper img {
        max-height: 130px;
    }
}
</style>
