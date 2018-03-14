<?php
/**
  * @file
  * Contains \Drupal\gameform\Form\GameForm
  */

namespace Drupal\GameForm\Form;

// way too many 'uses' but trying to see how to get this thing to work
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;

/**
  *  Provides SNTrack Email form
  */
class GameForm extends FormBase {

  // Not sure I'm doing this correctly
  public function __construct() {
    $this->renderer = \Drupal::service('renderer');
    $this->response = new AjaxResponse();
  }

  public function getFormId() {
    return 'GameFrom_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get Teams and Classes they are a part of
    $return = $this->teamList();

    // Teams & Classes
    $options = $return[0];
    $class = $return[1];

    // Game Date
    $form['game_date'] = array(
      '#title' => t('Game Date'),
      '#type' => 'date',
      // '#required' => TRUE
    );

    // Team Class
    $form['game_class'] = array(
      '#title' => t('Game Class'),
      '#type' => 'select',
      '#options' =>  array_unique(array_filter($class)),
      // '#required' => TRUE
    );

    // There are two teams, 1 & 2, so we loop to create both team drop downs
    // Teams
    for($i = 1; $i < 3; $i++) {
        $form["team{$i}"] = [
          '#title' => t('Team ' . $i),
          '#type' => 'select',
          '#options'      => $options,
          // '#required' => TRUE,
          '#prefix' => "<div class=\"team-{$i}-roster\">",
          '#ajax'         => [
            'event' => 'change',
            'callback'  => '::getRosterTeam' . $i,
            'wrapper'   => "team-{$i}-roster",
            'effect' => 'fade',
            'method' => 'replace',
            'progress' => [
              'type' => 'throbber',
              'message' => "team " . $i . "...",
            ],
          ],
          '#suffix' => "</div>",
        ];

    }

    // For each team there is a list of players per team.
    // Putting in a text field that will be replaced by a table of fields for each player on the team
    // But for starting, this is more or less a placeholder
    for($i = 1; $i < 3; $i++) {
      $form["team{$i}roster"] = [
        '#type' => 'textfield',
        '#value' => "Team {$i} Roster",
        '#size' => 10,
        '#attributes' => [
          'id' => ["team-{$i}-roster"],
        ],
      ];
    }

    $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit')
    );

    return $form;
  }


  // Callback that calls the method teamData()
  public function getRosterTeam1(array &$form, FormStateInterface $form_state) {
      $new_element = $this->teamData(1, $form_state->getValue('team1'));
      $this->response->addCommand(new ReplaceCommand('#team-1-roster', $this->renderer->render($new_element)));
      return $this->response;
  }

  public function getRosterTeam2(array &$form, FormStateInterface $form_state) {
      $new_element = $this->teamData(2, $form_state->getValue('team2'));
      $this->response->addCommand(new ReplaceCommand('#team-2-roster', $this->renderer->render($new_element)));
      return $this->response;
  }

  // Submit that badboy
  public function submitForm(array &$form, FormStateInterface $form_state) {
    print '<pre>';
    print_r($form_state->getValues());
    die();
  }

  // teamList
  // Create an array of Teams and their associated classes
  private function teamList() {
    // Create an array of Teams
    $teamQuery = \Drupal::entityQuery('node', 'n')
      ->condition('type', 'team', '=');
    $options[] = ' -- Select -- ';
    $teams = entity_load_multiple('node', $teamQuery->execute());
    foreach ($teams as $nid => $team) {
        $node = Node::load($nid);
        $class[] = $node->field_team_class->value;
        $options[$node->id()] = $node->title->value;
      }
    return [$options, $class];
  }

  // teamData method
  // This selects all the players in the team and creates a table
  // of player name, and the inputs to replace the textfield placeholder
  //
  // I'm not a fan of how the table is rendered, but I won't work on this
  // until I can actually get the form to work
  private function teamData($team, $data) {
    $query = db_select('node__field_team_player', 't')
                ->fields('t', ['field_team_player_target_id'])
                ->condition('entity_id', $data, '=')
                ->execute()
                ->fetchAll();
    $player = [];

    $stats = ['assists', 'goals', 'pim', 'plusminus', 'shots'];
    $form["team{$team}roster"]['prefix'] = [
       '#prefix' => "<div class=\"team{$team}roster\"><strong>Team {$team} Roster</strong><table><tr><th>Name / Number</th><th>Assits</th><th>Goals</th><th>Pim</th><th>+/-</th><th>Shots</th></tr>",
    ];

    foreach ($query as $key => $value) {
        $node = Node::load($value->field_team_player_target_id);
          $form["team{$team}roster"][]['name'] = [
            '#prefix' => "<div class=\"team{$team}rosterrow\"><tr class=\"team{$team}row\"><td>",
            '#markup' => $node->field_player_number->value . ' :: ' . $node->title->value,
            '#size' => 25,
            '#attributes' => [
              'id' => ["team-{$team}-roster-{$node->field_player_number->value}"],
            ],
          ];
        foreach ($stats as $stat) {
            $form["team{$team}roster"][][$stat] = [
              '#type' => 'textfield',
              '#size' => 6,
              '#prefix' => '<td>',
              '#suffix' => '</td>',
              '#attributes' => [
                'id' => ["team-{$team}-roster-{$node->field_player_number->value}"],
              ],
            ];
          }
        $form["team{$team}roster"][][$stat] = [
          '#suffix' => '</td></tr></div>',
        ];
      }

     $form["team{$team}roster"]['suffix'] = [
           '#suffix' => '</table></div>',
     ];

    return $form;
  }

}




/*

    // $playerNids = \Drupal::entityQuery('node')->condition('type','player')->execute();
    // $playerNodes =  Node::loadMultiple($playerNids);

    // $allPlayers = [' -- Select -- '];
    // foreach ($playerNodes as $key => $value) {
    //    $allPlayers[$key] = $value->field_player_number->value . ' ' . $value->title->value;
    // }



    $form['team1_roster'] = [
      '#title' => t('Team 1 Roster'),
      '#type' => 'select',
      '#options'      => $options,
      '#empty_option' => $this->t('-select-'),
      '#required' => TRUE,
    ];

    $form['team1_score'] = array(
      '#title' => t('Team 1 Score'),
      '#type' => 'textfield',
      '#maxlength' => 10,
      '#size' => 10,
      '#required' => TRUE
    );

    $form['team2'] = array(
      '#title' => t('Team 2'),
      '#type' => 'select',
      '#options' =>  $options,
      '#required' => TRUE
    );

    $form['team2_score'] = array(
      '#title' => t('Team 2 Score'),
      '#type' => 'textfield',
      '#maxlength' => 10,
      '#size' => 10,
      '#required' => TRUE
    );


  // public function getRoster(array $form, FormStateInterface $form_state){

  //   \Drupal::logger('GameForm')->notice('getRoster callback fired');

  //   $form['options'] = [1,2,3];
  //   return $form;
  // }



  public function getRosterTeam1(array &$form, FormStateInterface $form_state) {
    // $form_state->setRebuild();
    // $form_state->set('team-2-roster', ($content));
    // $form_state->setRebuild();

    $elem = $this->teamData(1, $form_state->getValue('team1'));
    $renderer = \Drupal::service('renderer');
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#team-1-roster', $renderer->render($elem)));
    return $response;
  }



  public function addplayerTeam1(array &$form, FormStateInterface $form_state) : array {

      \Drupal::logger('GameForm')->notice($form_state->getValue('team1roster'));

      $node = Node::load($form_state->getValue('allplayers1'));
      $player = $node->field_player_number->value . ' ' . $node->title->value;
      $elem = [
          '#type' => 'textarea',
          '#value' => $player,
          '#attributes' => [
            'id' => ['team-1-roster'],
          ],
        ];

      return $player;

  }

// public function getRoster(array &$form, FormStateInterface $form_state) : AjaxResponse {
//   \Drupal::logger('GameForm')->notice('getRoster callback fired');

//   $elem = [
//     '#type' => 'textfield',
//     '#size' => '60',
//     '#disabled' => TRUE,
//     '#value' => 'Hello, ' . $form_state->getValue('input') . '!',
//     '#attributes' => [
//       'id' => ['edit-output'],
//     ],
//   ];
//   $renderer = \Drupal::service('renderer');
//   $response = new AjaxResponse();
//   $response->addCommand(new ReplaceCommand('#edit-output', $renderer->render($elem)));
//   return $response;
// }
*/
