<?php

namespace Backend\Modules\Blog\Actions;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Backend\Core\Engine\Base\ActionEdit as BackendBaseActionEdit;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Core\Engine\Form as BackendForm;
use Backend\Core\Engine\Meta as BackendMeta;
use Backend\Core\Language\Language as BL;
use Backend\Modules\Blog\Engine\Model as BackendBlogModel;

/**
 * This is the edit category action, it will display a form to edit an existing category.
 */
class EditCategory extends BackendBaseActionEdit
{
    /**
     * Execute the action
     */
    public function execute()
    {
        // get parameters
        $this->id = $this->getParameter('id', 'int');

        // does the item exists
        if ($this->id !== null && BackendBlogModel::existsCategory($this->id)) {
            parent::execute();
            $this->getData();
            $this->loadForm();
            $this->validateForm();
            $this->parse();
            $this->display();
        } else {
            // no item found, throw an exception, because somebody is fucking with our URL
            $this->redirect(BackendModel::createURLForAction('Index') . '&error=non-existing');
        }
    }

    /**
     * Get the data
     */
    private function getData()
    {
        $this->record = BackendBlogModel::getCategory($this->id);
    }

    /**
     * Load the form
     */
    private function loadForm()
    {
        // create form
        $this->frm = new BackendForm('editCategory');

        // create elements
        $this->frm->addText('title', $this->record['title'], null, 'form-control title', 'form-control danger title');

        // meta object
        $this->meta = new BackendMeta($this->frm, $this->record['meta_id'], 'title', true);

        // set callback for generating a unique URL
        $this->meta->setURLCallback('Backend\Modules\Blog\Engine\Model', 'getURLForCategory', array($this->record['id']));
    }

    /**
     * Parse the form
     */
    protected function parse()
    {
        parent::parse();

        $this->tpl->assign('item', $this->record);

        // delete allowed?
        $this->tpl->assign(
            'showBlogDeleteCategory',
            BackendBlogModel::deleteCategoryAllowed($this->id) && BackendModel::createURLForAction('DeleteCategory')
        );
    }

    /**
     * Validate the form
     */
    private function validateForm()
    {
        if ($this->frm->isSubmitted()) {
            // cleanup the submitted fields, ignore fields that were added by hackers
            $this->frm->cleanupFields();

            // validate fields
            $this->frm->getField('title')->isFilled(BL::err('TitleIsRequired'));

            // validate meta
            $this->meta->validate();

            if ($this->frm->isCorrect()) {
                // build item
                $item['id'] = $this->id;
                $item['title'] = $this->frm->getField('title')->getValue();
                $item['meta_id'] = $this->meta->save(true);

                // update the item
                BackendBlogModel::updateCategory($item);

                // trigger event
                BackendModel::triggerEvent($this->getModule(), 'after_edit_category', array('item' => $item));

                // everything is saved, so redirect to the overview
                $this->redirect(
                    BackendModel::createURLForAction('Categories') . '&report=edited-category&var=' .
                    rawurlencode($item['title']) . '&highlight=row-' . $item['id']
                );
            }
        }
    }
}
