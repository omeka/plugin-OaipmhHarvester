<?php

class OaipmhHarvester_Form_Harvest extends Omeka_Form
{
    public function init()
    {
        parent::init();
        $this->addElement('text', 'base_url', array(
            'label' => 'Base Url',
            'description' => 'The base URL of the OAI-PMH data provider.',
            'size' => 60,
        ));
        $this->addElement('submit', 'submit_view_sets', array(
            'label' => 'View Sets',
            'class' => 'submit-medium',
        ));
    }
}
