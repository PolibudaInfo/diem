<?php

require_once(dmOs::join(sfConfig::get('dm_core_dir'), 'lib/vendor/gapi/gapi.class.php'));

class dmGapi
{
  protected
  $cacheManager,
  $reportId,
  $defaultReportOptions;
  /** @var \gapi */
  protected $gapi;

  public function __construct(dmCacheManager $cacheManager)
  {
    $this->cacheManager = $cacheManager;
  }

  /**
   * Set up authenticate with Google and get auth_token
   *
   * @param String $email
   * @param String $password
   * @param String $token
   * @return $this
   */
  public function authenticate($email, $password)
  {
    if (!($email && $password))
    {
      throw new dmGapiException('No google analytics account configured');
    }

    $this->reportId = null;

    try
    {
      $this->gapi = new \gapi($email, $password);
    }
    catch(Exception $e)
    {
      throw new dmGapiException('GAPI: Failed to authenticate with email '.$email.'. Please configure email and keyfile in the admin configuration panel');
    }

    $this->getReportId();

    return $this;
  }

  public function getTotalPageViews()
  {
    $report = $this->getReport(array(
      'dimensions'  => array('year'),
      'metrics'     => array('pageviews')
    ));

    $pageviews = 0;
    foreach($report as $entry)
    {
      $pageviews += $entry->get('pageviews');
    }

    unset($report);

    return $pageviews;
  }

  public function getReport(array $options)
  {
    $options = array_merge($this->getDefaultReportOptions(), $options);

    return $this->requestReportData(
    $this->getReportId(),
    $options['dimensions'],
    $options['metrics'],
    $options['sort_metric'],
    $options['filter'],
    $options['start_date'],
    $options['end_date'],
    $options['start_index'],
    $options['max_results']
    );
  }

  public function getDefaultReportOptions()
  {
    if (null === $this->defaultReportOptions)
    {
      $this->defaultReportOptions = array(
        'dimensions'  => array(),
        'metrics'     => array(),
        'sort_metric' => null,
        'filter'      => null,
        'start_date'  => date('Y-m-d',strtotime('11 months ago')),
        'end_date'    => null,
        'start_index' => 1,
        'max_results' => 30
      );
    }

    return $this->defaultReportOptions;
  }

  public function getReportId()
  {
    if ($this->reportId)
    {
      return $this->reportId;
    }

    if (!$gaKey = dmConfig::get('ga_key'))
    {
      throw new dmGapiException('You must configure a ga_key in the configuration panel');
    }

    $start_index = 1;

    while($accounts = $this->requestAccountData($start_index++))
    {
      foreach($accounts as $account)
      {
        if ($account->getId() === $gaKey)
        {
          return $this->reportId = $account->getProfileId();
        }
      }
    }

    throw new dmGapiException('Current report not found for ga key : '.$gaKey);
  }

  public function setCacheManager(dmCacheManager $cacheManager)
  {
    $this->cacheManager = $cacheManager;
  }

  /**
   * Request account data from Google Analytics
   *
   * @param Int $start_index OPTIONAL: Start index of results
   * @param Int $max_results OPTIONAL: Max results returned
   */
  public function requestAccountData($start_index=1, $max_results=20)
  {
    if ($this->cacheManager)
    {
      $cacheKey = 'account-data-'.$start_index.'-to-'.$max_results;

      if ($this->cacheManager->getCache('gapi/request')->has($cacheKey))
      {
        return $this->cacheManager->getCache('gapi/request')->get($cacheKey);
      }
    }

    try
    {
      $result = $this->gapi->requestAccountData($start_index, $max_results);
    }
    catch(Exception $e)
    {
      throw new dmGapiException($e->getMessage());
    }

    if ($this->cacheManager)
    {
      $this->cacheManager->getCache('gapi/request')->set($cacheKey, $result);
    }

    return $result;
  }

  /**
   * Request report data from Google Analytics
   *
   * $report_id is the Google report ID for the selected account
   *
   * $parameters should be in key => value format
   *
   * @param String $report_id
   * @param Array $dimensions Google Analytics dimensions e.g. array('browser')
   * @param Array $metrics Google Analytics metrics e.g. array('pageviews')
   * @param Array $sort_metric OPTIONAL: Dimension or dimensions to sort by e.g.('-visits')
   * @param String $filter OPTIONAL: Filter logic for filtering results
   * @param String $start_date OPTIONAL: Start of reporting period
   * @param String $end_date OPTIONAL: End of reporting period
   * @param Int $start_index OPTIONAL: Start index of results
   * @param Int $max_results OPTIONAL: Max results returned
   */
  public function requestReportData($report_id, $dimensions, $metrics, $sort_metric=null, $filter=null, $start_date=null, $end_date=null, $start_index=1, $max_results=30)
  {
    if ($this->cacheManager)
    {
      $cacheKey = 'report-data-'.md5(serialize(func_get_args()));

      if ($this->cacheManager->getCache('gapi/request')->has($cacheKey))
      {
        return $this->cacheManager->getCache('gapi/request')->get($cacheKey);
      }
    }

    $result = $this->gapi->requestReportData($report_id, $dimensions, $metrics, $sort_metric, $filter, $start_date, $end_date, $start_index, $max_results);

    if ($this->cacheManager)
    {
      $this->cacheManager->getCache('gapi/request')->set($cacheKey, $result);
    }

    return $result;
  }
}

class gapi2 extends gapi
{
  /**
   * Report Object Mapper to convert the XML to array of useful PHP objects
   *
   * @param String $xml_string
   * @return Array of gapiReportEntry objects
   */
  protected function reportObjectMapper($json_string)
  {
    $json = json_decode($json_string, true);

    $this->results = null;
    $results = array();

    $report_aggregate_metrics = array();

    //Load root parameters

    // Start with elements from the root level of the JSON that aren't themselves arrays.
    $report_root_parameters = array_filter($json, function($var){
       return !is_array($var);
    });

    // Get the items from the 'query' object, and rename them slightly.
    foreach($json['query'] as $index => $value) {
      $new_index = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $index))));
      $report_root_parameters[$new_index] = $value;
    }

    // Now merge in the profileInfo, as this is also mostly useful.
    array_merge($report_root_parameters, $json['profileInfo']);

    //Load result aggregate metrics

    foreach($json['totalsForAllResults'] as $index => $metric_value) {
      //Check for float, or value with scientific notation
      if (preg_match('/^(\d+\.\d+)|(\d+E\d+)|(\d+.\d+E\d+)$/', $metric_value)) {
        $report_aggregate_metrics[str_replace('ga:', '', $index)] = floatval($metric_value);
      } else {
        $report_aggregate_metrics[str_replace('ga:', '', $index)] = intval($metric_value);
      }
    }

    //Load result entries
    if(isset($json['rows'])){
      foreach($json['rows'] as $row) {
        $metrics = array();
        $dimensions = array();
        foreach($json['columnHeaders'] as $index => $header) {
          switch($header['columnType']) {
            case 'METRIC':
              $metric_value = $row[$index];

              //Check for float, or value with scientific notation
              if(preg_match('/^(\d+\.\d+)|(\d+E\d+)|(\d+.\d+E\d+)$/',$metric_value)) {
                $metrics[str_replace('ga:', '', $header['name'])] = floatval($metric_value);
              } else {
                $metrics[str_replace('ga:', '', $header['name'])] = intval($metric_value);
              }
              break;
            case 'DIMENSION':
              $dimensions[str_replace('ga:', '', $header['name'])] = strval($row[$index]);
              break;
            default:
              throw new Exception("GAPI: Unrecognized columnType '{$header['columnType']}' for columnHeader '{$header['name']}'");
            }
          }
          $results[] = new gapiReportEntry($metrics, $dimensions);
        }
    }

    $this->report_root_parameters = $report_root_parameters;
    $this->report_aggregate_metrics = $report_aggregate_metrics;
    $this->results = $results;

    return $results;
  }
}
