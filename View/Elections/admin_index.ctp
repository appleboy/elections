<div id="ElectionsAdminIndex">
    <h2><?php echo __('Elections', true); ?></h2>
    <div class="btn-group">
        <?php echo $this->Html->link(__('Add', true), array('action' => 'add', $parentId), array('class' => 'btn dialogControl')); ?>
    </div>
    <div class="clearfix"></div>
    <?php
    if (!empty($parents)) {
        $this->Html->addCrumb('最上層', array('controller' => 'elections', 'action' => 'index'));
        foreach ($parents AS $parent) {
            if ($parent['Election']['rght'] - $parent['Election']['lft'] !== 1) {
                $this->Html->addCrumb($parent['Election']['name'], array(
                    'action' => 'index', $parent['Election']['id'])
                );
            } else {
                $this->Html->addCrumb($parent['Election']['name'], array(
                    'controller' => 'candidates',
                    'action' => 'index', $parent['Election']['id'])
                );
            }
        }
        echo $this->Html->getCrumbs();
    }
    ?>
    <div class="paging"><?php echo $this->element('paginator'); ?></div>
    <table class="table table-bordered" id="ElectionsAdminIndexTable">
        <thead>
            <tr>
                <?php
                if (!empty($op)) {
                    echo '<th>&nbsp;</th>';
                }
                ?>
                <th><?php echo $this->Paginator->sort('Election.name', 'Name', array('url' => $url)); ?></th>
                <th class="actions"><?php echo __('Action', true); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 0;
            foreach ($items as $item) {
                $class = null;
                if ($i++ % 2 == 0) {
                    $class = ' class="altrow"';
                }
                ?>
                <tr<?php echo $class; ?>>
                    <?php
                    if (!empty($op)) {
                        echo '<td>';
                        $options = array('value' => $item['Election']['id'], 'class' => 'habtmSet');
                        if ($item['option'] == 1) {
                            $options['checked'] = 'checked';
                        }
                        echo $this->Form->checkbox('Set.' . $item['Election']['id'], $options);
                        echo '<div id="messageSet' . $item['Election']['id'] . '"></div></td>';
                    }
                    ?>
                    <td><?php
                        echo $this->Html->link($item['Election']['name'], array('action' => 'index', $item['Election']['id']));
                        ?></td>
                    <td class="actions">
                        <?php echo $this->Html->link(__('View', true), array('action' => 'view', $item['Election']['id'])); ?>
                        <?php echo $this->Html->link(__('Edit', true), array('action' => 'edit', $item['Election']['id'])); ?>
                        <?php echo $this->Html->link(__('Delete', true), array('action' => 'delete', $item['Election']['id']), null, __('Delete the item, sure?', true)); ?>
                        <?php
                        if($item['Election']['rght'] - $item['Election']['lft'] === 1) {
                            echo ' ' . $this->Html->link('候選人', array('controller' => 'candidates', 'action' => 'index', $item['Election']['id']));
                            echo ' ' . $this->Html->link('新增候選人', array('controller' => 'candidates', 'action' => 'add', $item['Election']['id']));
                        }
                        ?>
                    </td>
                </tr>
            <?php } // End of foreach ($items as $item) {  ?>
        </tbody>
    </table>
    <div class="paging"><?php echo $this->element('paginator'); ?></div>
    <div id="ElectionsAdminIndexPanel"></div>
    <script type="text/javascript">
        //<![CDATA[
        $(function() {
            $('#ElectionsAdminIndexTable th a, #ElectionsAdminIndex div.paging a').click(function() {
                $('#ElectionsAdminIndex').parent().load(this.href);
                return false;
            });
<?php
if (!empty($op)) {
    $remoteUrl = $this->Html->url(array('action' => 'habtmSet', $parentId, $foreignModel, $foreignId));
    ?>
                $('#ElectionsAdminIndexTable input.habtmSet').click(function() {
                    var remoteUrl = '<?php echo $remoteUrl; ?>/' + this.value + '/';
                    if (this.checked == true) {
                        remoteUrl = remoteUrl + 'on';
                    } else {
                        remoteUrl = remoteUrl + 'off';
                    }
                    $('div#messageSet' + this.value).load(remoteUrl);
                });
    <?php
}
?>
        });
        //]]>
    </script>
</div>