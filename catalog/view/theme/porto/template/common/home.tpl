<?php echo $header;
$theme_options = $registry->get('theme_options');
$config = $registry->get('config'); ?>
<?php $grid_center = 12;
if($column_left != '') $grid_center = $grid_center-3;
if($column_right != '') $grid_center = $grid_center-3;

require_once( DIR_TEMPLATE.$config->get('theme_' . $config->get('config_theme') . '_directory')."/lib/module.php" );
$modules = new Modules($registry); ?>

<!-- MAIN CONTENT
    ================================================== -->
<script type="text/javascript">
    var logged_in = '<?php echo($logged_in) ?>';
</script>
<div class="main-content home" id="content">
    <div class="background-content"></div>
    <div class="background">
        <div class="shadow"></div>
        <div class="pattern">
            <div class="container">
                <?php
                $preface_left = $modules->getModules('preface_left');
                $preface_right = $modules->getModules('preface_right');
                ?>
                <?php if( count($preface_left) || count($preface_right) ) { ?>
                <div class="row">
                    <div class="col-sm-9">
                        <?php
                        if( count($preface_left) ) {
                            foreach ($preface_left as $module) {
                                echo $module;
                            }
                        } ?>
                    </div>

                    <div class="col-sm-3">
                        <?php
                        if( count($preface_right) ) {
                            foreach ($preface_right as $module) {
                                echo $module;
                            }
                        } ?>
                    </div>
                </div>
                <?php } ?>

                <?php
                $preface_fullwidth = $modules->getModules('preface_fullwidth');
                if( count($preface_fullwidth) ) { ?>
                <div class="row">
                    <div class="col-sm-12">
                        <?php
                            foreach ($preface_fullwidth as $module) {
                                echo $module;
                            }
                        ?>
                    </div>
                </div>
                <?php } ?>

                <div class="row">
                    <?php $columnleft = $modules->getModules('column_left'); ?>
                    <?php $grid_center = 12; if( count($columnleft) ) { $grid_center = 9; } ?>
                    <div class="col-md-<?php echo $grid_center; ?> align-right">
                        <?php
                        $content_big_column = $modules->getModules('content_big_column');
                        if( count($content_big_column) ) {
                            foreach ($content_big_column as $module) {
                                echo $module;
                            }
                        } ?>

                        <div class="row">
                            <?php
                            $grid_content_top = 12;
                            $grid_content_right = 3;
                            $column_right = $modules->getModules('column_right');
                            if( count($column_right) ) {
                                if($grid_center == 9) {
                                    $grid_content_top = 8;
                                    $grid_content_right = 4;
                                } else {
                                    $grid_content_top = 9;
                                    $grid_content_right = 3;
                                }
                            }
                            ?>
                            <div class="col-md-<?php echo $grid_content_top; ?>">
                                <?php
                                $content_top = $modules->getModules('content_top');
                                if( count($content_top) ) {
                                    foreach ($content_top as $module) {
                                        echo $module;
                                    }
                                } ?>
                            </div>

                            <?php if( count($column_right) ) { ?>
                            <div class="col-md-<?php echo $grid_content_right; ?>">
                                <?php foreach ($column_right as $module) {
                                    echo $module;
                                } ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>

                    <?php if( count($columnleft) ) { ?>
                    <div class="col-md-3" id="column_left">
                        <?php
                        foreach ($columnleft as $module) {
                            echo $module;
                        }
                        ?>
                    </div>
                    <?php } ?>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <?php
                        $contentbottom = $modules->getModules('content_bottom');
                        if( count($contentbottom) ) { ?>
                            <?php
                            foreach ($contentbottom as $module) {
                                echo $module;
                            }
                            ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php echo $footer; ?>