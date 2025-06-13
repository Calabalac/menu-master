<?php
/**
 * Template for image review modal
 */
?>
<div id="cm-image-review-modal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title"><?php _e('Image Review', 'catalog-master'); ?></p>
            <button class="delete" aria-label="close"></button>
        </header>
        
        <section class="modal-card-body">
            <div class="columns">
                <!-- Local image -->
                <div class="column">
                    <h4 class="title is-5"><?php _e('Current Image', 'catalog-master'); ?></h4>
                    <div class="card">
                        <div class="card-image">
                            <figure class="image" id="cm-local-image">
                                <!-- Image will be inserted here -->
                            </figure>
                        </div>
                        <div class="card-content">
                            <div class="content" id="cm-local-info">
                                <!-- Image info will be inserted here -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Remote image -->
                <div class="column">
                    <h4 class="title is-5"><?php _e('New Image', 'catalog-master'); ?></h4>
                    <div class="card">
                        <div class="card-image">
                            <figure class="image" id="cm-remote-image">
                                <!-- Image will be inserted here -->
                            </figure>
                        </div>
                        <div class="card-content">
                            <div class="content" id="cm-remote-info">
                                <!-- Image info will be inserted here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <footer class="modal-card-foot">
                            <button class="btn btn-success" id="cm-keep-remote">
                <?php _e('Use New Image', 'catalog-master'); ?>
            </button>
            <button class="button" id="cm-keep-local">
                <?php _e('Keep Current', 'catalog-master'); ?>
            </button>
        </footer>
    </div>
</div>
