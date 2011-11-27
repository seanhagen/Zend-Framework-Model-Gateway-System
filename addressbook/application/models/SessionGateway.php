<?php
class SessionGateway extends BaseGateway {
  /**
   * Table name
   *
   * @var string
   */
  protected $_name = "sessions";

  /**
   * Delete old user sessions from table.
   * 
   * @param none
   * @return void
   */
  public function clearOldSessions($userId)
  {
    $this->getSession()
      ->byUserId($userId)
      ->deleteResults(true); // "true" for realDelete() instead of delete()
  } 
}