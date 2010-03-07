<?php

/**
 * BaseDmArea
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property integer $dm_layout_id
 * @property integer $dm_page_view_id
 * @property enum $type
 * @property DmLayout $Layout
 * @property DmPageView $PageView
 * @property Doctrine_Collection $Zones
 * 
 * @method integer             getDmLayoutId()      Returns the current record's "dm_layout_id" value
 * @method integer             getDmPageViewId()    Returns the current record's "dm_page_view_id" value
 * @method enum                getType()            Returns the current record's "type" value
 * @method DmLayout            getLayout()          Returns the current record's "Layout" value
 * @method DmPageView          getPageView()        Returns the current record's "PageView" value
 * @method Doctrine_Collection getZones()           Returns the current record's "Zones" collection
 * @method DmArea              setDmLayoutId()      Sets the current record's "dm_layout_id" value
 * @method DmArea              setDmPageViewId()    Sets the current record's "dm_page_view_id" value
 * @method DmArea              setType()            Sets the current record's "type" value
 * @method DmArea              setLayout()          Sets the current record's "Layout" value
 * @method DmArea              setPageView()        Sets the current record's "PageView" value
 * @method DmArea              setZones()           Sets the current record's "Zones" collection
 * 
 * @package    retest
 * @subpackage model
 * @author     Your name here
 * @version    SVN: $Id: Builder.php 7200 2010-02-21 09:37:37Z beberlei $
 */
abstract class BaseDmArea extends myDoctrineRecord
{
    public function setTableDefinition()
    {
        $this->setTableName('dm_area');
        $this->hasColumn('dm_layout_id', 'integer', null, array(
             'type' => 'integer',
             'notnull' => false,
             ));
        $this->hasColumn('dm_page_view_id', 'integer', null, array(
             'type' => 'integer',
             'notnull' => false,
             ));
        $this->hasColumn('type', 'enum', null, array(
             'type' => 'enum',
             'notnull' => true,
             'values' => 
             array(
              0 => 'content',
              1 => 'top',
              2 => 'bottom',
              3 => 'left',
              4 => 'right',
             ),
             'default' => 'content',
             ));
    }

    public function setUp()
    {
        parent::setUp();
        $this->hasOne('DmLayout as Layout', array(
             'local' => 'dm_layout_id',
             'foreign' => 'id',
             'onDelete' => 'CASCADE'));

        $this->hasOne('DmPageView as PageView', array(
             'local' => 'dm_page_view_id',
             'foreign' => 'id',
             'onDelete' => 'CASCADE'));

        $this->hasMany('DmZone as Zones', array(
             'local' => 'id',
             'foreign' => 'dm_area_id'));
    }
}