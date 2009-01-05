<?php
class OaipmhHarvesterSetStatusTable extends Omeka_Db_Table
{
	function findIdByName($name)
	{
		$select = $this->getSelect();
		$select->reset('columns')
			   ->from(array(), array('id'))
			   ->where('`name` = ?', $name);
		return $this->fetchOne($select);
	}
}