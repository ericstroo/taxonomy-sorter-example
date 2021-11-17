<?php
/**
 * Plugin Name: Taxonomy Sorter Example
 * Description: Example plugin to showcase how to sort posts in a taxonomy.
 * Author:      Nelio Software, Eric Stroo
 * Author URI:  https://neliosoftware.com
 * Version:     1.0.0
 */

class Sort {

  private $wpdb;

  function __construct($content_type, $taxonomy) {
    $this->content_type = $content_type;
    $this->taxonomy = $taxonomy;

    global $wpdb;

    $this->wpdb = $wpdb;
    add_filter( 'posts_orderby', [$this, 'sort_questions_in_topic' ], 99, 2 );
    add_action( 'admin_menu', [$this, 'add_sorting_page'] );

  }
  function sort_questions_in_topic( $orderby, $query ) {

    if ( ! [$this, 'is_topic_tax_query']( $query ) ) return;

    return "{$this->wpdb->term_relationships}.term_order ASC";
  }

  function is_topic_tax_query( $query ) {
    if ( empty( $query->tax_query ) ) return;
    if ( empty( $query->tax_query->queries ) ) return;
    return in_array(
    $query->tax_query->queries[0]['taxonomy'],
    [ $this->taxonomy ],
    true
    );
  }

  function add_sorting_page() {
    if ($this->content_type == 'post') {
      add_submenu_page(
        'edit.php',
        'Sort',
        'Sort',
        'edit_others_posts',
        'cc-sorter',
        [$this, 'render_question_sorter']
      );
    }
    else {
      add_submenu_page(
        'edit.php?post_type='.$this->content_type,
        'Sort',
        'Sort',
        'edit_others_posts',
        'cc-sorter',
        [$this, 'render_question_sorter']
      );
    }

  }

  function render_question_sorter() {
    printf(
      '<div class="wrap"><h1>%s</h1>',
      __( 'Sort!', 'tse' )
    );

    $terms = get_terms( $this->taxonomy );
    $this->render_select( $terms );
    foreach ( $terms as $term ) {
      $this->render_questions_in_term( $this->content_type, $this->taxonomy, $term );
    }
    $this->render_script();

    echo '</div>';
  }

  function render_select( $terms ) {
    echo '<select id="topic">';
    foreach ( $terms as $term ) {
      printf(
        '<option value="%s">%s</option>',
        esc_attr( $term->slug ),
        esc_html( $term->name )
      );
    }
    echo '</select>';
  }

  function render_questions_in_term( $type, $taxonomy, $term ) {
    $style = 'max-width: 50em; padding: 1em; background: white; margin: 1em 0; display: none;';
    printf(
      '<div id="%s" class="question-set" style="%s">',
      esc_attr( "{$term->slug}-questions" ),
      esc_attr( $style )
    );

    $query = new WP_Query(
      array(
        'post_type'      => $type,
        'posts_per_page' => -1,
        'tax_query'      => array(
          array(
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term->term_id,
            'orderby'  => 'term_order',

          ),
        ),
      )
    );

    printf(
      '<div class="sorted-questions-in-%d sortable">',
      $term->term_id
    );
    $style = 'background: #fafafc; border-left: 0.5em solid #0073aa; padding: 0.5em; margin-bottom: 0.5em; cursor: pointer; user-select: none;';
    while ( $query->have_posts() ) {
      $query->the_post();
      global $post;
      printf(
        '<div class="question" style="%s" data-question-id="%d">%s</div>',
        esc_attr( $style ),
        $post->ID,
        esc_html( $post->post_title )
      );
    }//end foreach
    echo '</div>';

    echo '<div style="text-align: right; padding-top: 1em;">';
    printf(
      '<input class="button save-question-order" type="button" data-term-id="%d" data-term-name="%s" value="%s" />',
      $term->term_id,
      esc_attr( $term->name ),
      esc_attr( "Save {$term->name}" )
    );
    echo '</div>';

    echo '</div>';
  }

  function render_script() { ?>
    <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script type="text/javascript">
    ( function() {

      const select = document.getElementById( 'topic' );
      const questionSets = [ ...document.querySelectorAll( '.question-set' ) ];
      select.addEventListener( 'change', () => {
        questionSets.forEach( ( set ) => set.style.display = 'none' );
        document.getElementById( `${ select.value }-questions` ).style.display = 'block';
      } );

      document.querySelector( '.question-set' ).style.display = 'block';

      $( '.sortable' ).sortable();

      [ ...document.querySelectorAll( '.button.save-question-order' ) ].forEach( ( button ) => {
        button.addEventListener( 'click', () => {
          const termId = button.getAttribute( 'data-term-id' );
          const termName = button.getAttribute( 'data-term-name' );

          button.value = <?php echo wp_json_encode( "Saving %s..." ); ?>.replace( '%s', termName );
          button.disabled = true;

          const sortedQuestionIds = [ ...document.querySelectorAll( `.sorted-questions-in-${ termId } .question` ) ].map( ( el ) => el.getAttribute( 'data-question-id' ) );
          console.log(sortedQuestionIds);
          $.ajax( {
            url: ajaxurl,
            method: 'POST',
            data: {
              action: 'save_tax_sorting',
              ids: sortedQuestionIds,
              termId,
            },
          } ).always( () => {
            button.value = <?php echo wp_json_encode( "Save %s" ); ?>.replace( '%s', termName );
            button.disabled = false;
          } );
        } );
      } );
    } )();
    </script>
    <?php
  }
}

function save_tax_sorting() { ?>
  <script>
  console.log('hi');
  </script> <?php
  $question_ids = isset( $_POST['ids'] ) ? $_POST['ids'] : [];
  if ( ! is_array( $question_ids ) ) {
    echo -1;
    wp_die();
  }//end if
  $question_ids = array_values( array_map( 'absint', $question_ids ) );

  $term_id = absint( $_POST['termId'] );
  if ( ! $term_id ) {
    echo -2;
    wp_die();
  }//end if

  global $wpdb;
  foreach ( $question_ids as $order => $question ) {
    ++$order;
    $wpdb->update(
      $wpdb->term_relationships,
      array( 'term_order' => $order ),
      array(
        'object_id'        => $question,
        'term_taxonomy_id' => $term_id,
      )
    );
  } //end foreach
  echo 0;
  wp_die();
}
add_action( 'wp_ajax_save_tax_sorting', 'save_tax_sorting' );

