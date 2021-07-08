<?php

class OaipmhHarvester_Form_Harvest extends Omeka_Form
{
    public function init()
    {
        parent::init();
        $this->addElement('text', 'base_url', array(
            'label' => __('Base URL'),
            'description' => __('The base URL of the OAI-PMH data provider.'),
            'size' => 60,
        ));
        
        $this->applyOmekaStyles();
        $this->setAutoApplyOmekaStyles(false);
        
        $this->addElement('submit', 'submit_view_sets', array(
            'label' => __('View Sets'),
            'class' => 'submit submit-medium',
            'decorators' => (array(
                'ViewHelper', 
                array('HtmlTag', array('tag' => 'div', 'class' => 'field'))))
        ));
    }
}
