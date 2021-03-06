<div id="CandidatesAdminIndex">
    <h2><?php echo $tag['Tag']['name']; ?> :: 候選人</h2>
    <div class="clearfix"></div>
    <div class="col-md-12"><?php echo $this->Html->getCrumbs(); ?></div>
    <div class="paging col-md-4"><?php echo $this->element('paginator'); ?></div>
    <div class="clearfix"></div>
    <?php
    if (!empty($items)) {
        foreach ($items AS $candidate) {
            ?><div class="col-md-2">
                <a class="thumbnail text-center" href="<?php echo $this->Html->url('/candidates/view/' . $candidate['Candidate']['id']); ?>">
                    <?php
                    if (empty($candidate['Candidate']['image'])) {
                        echo $this->Html->image('candidate-not-found.jpg', array('style' => 'width: 100px; border: 0px;'));
                    } else {
                        echo $this->Html->image('../media/' . $candidate['Candidate']['image'], array('style' => 'width: 100px; height: 100px; border: 0px;'));
                    }
                    ?>
                    <br /><?php echo $candidate['Candidate']['name']; ?>
                    <br /><?php echo $candidate['Election'][1]['Election']['name']; ?>
                </a>
            </div><?php
        }
    } else {
        echo ' ~ 目前沒有候選人資料 ~ ';
    }
    ?>
    <div class="clearfix"></div>
    <div class="paging"><?php echo $this->element('paginator'); ?></div>
    <div id="vanilla-comments"></div>
    <script type="text/javascript">
        var vanilla_forum_url = '<?php echo $this->Html->url('/../talk'); ?>'; // Required: the full http url & path to your vanilla forum
        var vanilla_identifier = '<?php echo $tag['Tag']['id']; ?>'; // Required: your unique identifier for the content being commented on
        var vanilla_url = '<?php echo $this->Html->url('/candidates/tag/' . $tag['Tag']['id'], true); ?>'; // Current page's url
        (function () {
            var vanilla = document.createElement('script');
            vanilla.type = 'text/javascript';
            var timestamp = new Date().getTime();
            vanilla.src = vanilla_forum_url + '/js/embed.js';
            (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(vanilla);
        })();
    </script>
</div>