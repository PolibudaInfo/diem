<?php

class dmGoogleAnalyticsForm extends dmForm
{
  protected
  $gapi;

  public function setGapi(dmGapi $gapi)
  {
    $this->gapi = $gapi;
  }

  public function configure()
  {
    $this->widgetSchema['key'] = new sfWidgetFormInputText();
    $this->validatorSchema['key'] = new sfValidatorString(array('required' => false));
    $this->widgetSchema->setHelp('key', dmDb::table('DmSetting')->findOneByName('ga_key')->description);
    $this->setDefault('key', dmConfig::get('ga_key'));
    
    $this->widgetSchema['email'] = new sfWidgetFormInputText();
    $this->validatorSchema['email'] = new sfValidatorEmail(array('required' => false));
    $this->widgetSchema->setHelp('email', 'Required to display google analytics data into Diem');
    $this->setDefault('email', dmConfig::get('ga_email'));

    $this->widgetSchema['keyfile'] = new sfWidgetFormInput();
    $this->validatorSchema['keyfile'] = new sfValidatorString(array('required' => false));
    $this->widgetSchema->setHelp('keyfile', 'Required to display google analytics data into Diem');
    $this->setDefault('keyfile', dmConfig::get('ga_keyfile'));

    $this->mergePostValidator(new sfValidatorCallback(array('callback' => array($this, 'tokenize'))));
  }

  public function tokenize($validator, $values)
  {
    if($values['email'] || $values['keyfile'])
    {
      try
      {
        $this->gapi->authenticate($values['email'], $values['keyfile']);
      }
      catch(dmGapiException $e)
      {
        // probably bad email/keyfile
        // throw an error bound to the password field
        throw new sfValidatorErrorSchema($validator, array('email' => new sfValidatorError($validator, 'Bad email or keyfile')));
      }
    }
    
    return $values;
  }

  public function save()
  {
    if($this->getValue('email') && $this->getValue('keyfile'))
    {
      dmConfig::set('ga_email', $this->getValue('email'));
      dmConfig::set('ga_keyfile', $this->getValue('keyfile'));
    }
  }
}
