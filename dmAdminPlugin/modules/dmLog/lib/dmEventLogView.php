<?php

class dmEventLogView extends dmLogView
{
  protected
  $rows = array(
    'time'     => 'renderTime',
    'user'     => 'renderUser',
    'action'   => 'renderAction',
    'type'     => 'renderType',
    'subject'  => 'renderSubject'
  );
  
  /*
   * Row renderers
   */
  protected function renderUser(dmEventLogEntry $entry)
  {
    return sprintf('%s%s<br /><span class=light>%s</span>',
      ($username = $entry->get('username')) ? sprintf('<strong class="mr10">%s</strong>', $username) : '',
      $entry->get('ip'),
      $entry->get('session_id')
    );
  }
  
  protected function renderTime(dmEventLogEntry $entry)
  {
    return str_replace(' CEST', '', $this->dateFormat->format($entry->get('time')));
//    return date('Y/m/d H:m:s', $entry->get('time'));
  }
  
  protected function renderSubject(dmEventLogEntry $entry)
  {
    return 'exception' === $entry->get('type')
    ? dmLinkTag::build('@dm_error')->param('search', $entry->get('subject'))->text($entry->get('subject'))
    : $this->i18n->__($entry->get('subject'));
  }
  
  protected function renderAction(dmEventLogEntry $entry)
  {
    return '<span class="block '.$this->getActionClass($entry->get('action')).'">'.$this->i18n->__($entry->get('action')).'</span>';
  }
  
  protected function renderType(dmEventLogEntry $entry)
  {
    return '<strong>'.$this->i18n->__(dmString::humanize($entry->get('type'))).'</strong>';
  }
  
  protected function getActionClass($action)
  {
    switch($action)
    {
      case 'create': $class = 's24 s24_add'; break;
      case 'update': $class = 's24 s24_edit'; break;
      case 'delete': $class = 's24 s24_delete'; break;
      case 'error':  $class = 's24 s24_error'; break;
      default:       $class = '';
    }
    
    return $class;
  }
  
  protected function doGetEntries(array $options)
  {
    return $this->log->getFilteredEntries($this->maxEntries, array($this, 'filterEntry'), $options);
  }
  
  public function filterEntry(array $data)
  {
    if ($data['action'] === 'error' && !$this->user->can('see_error_log'))
    {
      return false;
    }
    
    return true;
  }
}