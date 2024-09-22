<?php

defined('\ABSPATH') || exit;

/*
  Name: Specifications
 */
__('Specifications', 'content-egg-tpl');
?>

<?php foreach ($items as $key => $item) : ?>
  <div class="egg-container egg-specs">

    <?php if (empty($item['features'])) continue; ?>
    <table class='table table-condensed cegg-features-table'>
      <tbody>
        <?php foreach ($item['features'] as $feature) : ?>
          <tr>
            <td class='text-muted col-md-4 col-sm-4 col-xs-3'><?php echo esc_html(__($feature['name'], 'content-egg-tpl')) ?></td>
            <td><?php echo esc_html($feature['value']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; ?>