<?php

App::uses('AppController', 'Controller');
App::uses('Sanitize', 'Utility');

class CandidatesController extends AppController {

    public $name = 'Candidates';
    public $paginate = array();
    public $helpers = array();

    public function beforeFilter() {
        parent::beforeFilter();
        if (isset($this->Auth)) {
            $this->Auth->allow('index', 'add', 'view', 'edit', 's', 'tag', 'submits');
        }
    }

    public function submits() {
        $this->set('count', $this->Candidate->find('count', array(
                    'conditions' => array(
                        'Candidate.active_id IS NOT NULL',
                        'Candidate.is_reviewed' => '0',
                    ),
        )));
    }

    public function s() {
        $result = array();
        if (isset($this->request->query['term'])) {
            $keyword = Sanitize::clean($this->request->query['term']);
        }
        if (!empty($keyword)) {
            $result = $this->Candidate->find('all', array(
                'fields' => array('Candidate.id', 'Candidate.name', 'CandidatesElection.Election_id'),
                'conditions' => array(
                    'Candidate.active_id IS NULL',
                    'Candidate.name LIKE' => "%{$keyword}%",
                ),
                'limit' => 20,
                'joins' => array(
                    array(
                        'table' => 'candidates_elections',
                        'alias' => 'CandidatesElection',
                        'type' => 'inner',
                        'conditions' => array(
                            'CandidatesElection.Candidate_id = Candidate.id',
                        ),
                    ),
                ),
            ));
            foreach ($result AS $k => $v) {
                $result[$k]['jobTitle'] = '';
                $parents = $this->Candidate->Election->getPath($v['CandidatesElection']['Election_id'], array('name'));
                foreach ($parents AS $parent) {
                    $result[$k]['jobTitle'] .= $parent['Election']['name'];
                }
            }
        }
        $this->set('result', $result);
    }

    public function tag($tagId = '') {
        $tag = $this->Candidate->Tag->find('first', array(
            'conditions' => array('Tag.id' => $tagId,)
        ));
        if (!empty($tag)) {
            $scope = array(
                'Candidate.active_id IS NULL',
                'CandidatesTag.Tag_id' => $tagId,
            );

            $this->paginate['Candidate']['joins'] = array(
                array(
                    'table' => 'candidates_elections',
                    'alias' => 'CandidatesElection',
                    'type' => 'inner',
                    'conditions' => array(
                        'CandidatesElection.Candidate_id = Candidate.id',
                    ),
                ),
                array(
                    'table' => 'candidates_tags',
                    'alias' => 'CandidatesTag',
                    'type' => 'inner',
                    'conditions' => array(
                        'CandidatesTag.Candidate_id = Candidate.id',
                    ),
                ),
            );
            $this->paginate['Candidate']['order'] = array('Candidate.modified' => 'desc');
            $this->paginate['Candidate']['limit'] = 30;
            $this->paginate['Candidate']['fields'] = array('Candidate.id', 'Candidate.name', 'Candidate.image', 'CandidatesElection.Election_id');
            $items = $this->paginate($this->Candidate, $scope);
            $electionStack = array();
            foreach ($items AS $k => $item) {
                if (!isset($electionStack[$item['CandidatesElection']['Election_id']])) {
                    $electionStack[$item['CandidatesElection']['Election_id']] = $this->Candidate->Election->getPath($item['CandidatesElection']['Election_id'], array('id', 'name'));
                }
                $items[$k]['Election'] = $electionStack[$item['CandidatesElection']['Election_id']];
            }

            $this->set('title_for_layout', $tag['Tag']['name'] . ' 候選人');
            $this->set('items', $items);
            $this->set('url', array($tagId));
            $this->set('tag', $tag);
        } else {
            $this->redirect(array('controller' => 'areas'));
        }
    }

    function index($electionId = '') {
        $scope = array(
            'Candidate.active_id IS NULL',
        );

        if (!empty($electionId)) {
            $scope['CandidatesElection.Election_id'] = $electionId;
        }

        $this->paginate['Candidate']['joins'] = array(
            array(
                'table' => 'candidates_elections',
                'alias' => 'CandidatesElection',
                'type' => 'inner',
                'conditions' => array(
                    'CandidatesElection.Candidate_id = Candidate.id',
                ),
            ),
        );
        $this->paginate['Candidate']['order'] = array('Candidate.modified' => 'desc');
        $this->paginate['Candidate']['limit'] = 30;
        $this->paginate['Candidate']['fields'] = array('Candidate.id', 'Candidate.name', 'Candidate.image', 'CandidatesElection.Election_id');
        $items = $this->paginate($this->Candidate, $scope);
        $electionStack = array();
        foreach ($items AS $k => $item) {
            if (!isset($electionStack[$item['CandidatesElection']['Election_id']])) {
                $electionStack[$item['CandidatesElection']['Election_id']] = $this->Candidate->Election->getPath($item['CandidatesElection']['Election_id'], array('id', 'name'));
            }
            $items[$k]['Election'] = $electionStack[$item['CandidatesElection']['Election_id']];
        }
        $parents = $this->Candidate->Election->getPath($electionId);
        $c = array();
        if (!empty($parents)) {
            $c = Set::extract('{n}.Election.name', $parents);
        }

        $this->set('title_for_layout', implode(' > ', $c) . '候選人 @ ');
        $this->set('items', $items);
        $this->set('electionId', $electionId);
        $this->set('url', array($electionId));
        $this->set('parents', $parents);
    }

    function add($electionId = '') {
        if (!empty($electionId)) {
            if (!empty($this->data)) {
                $dataToSave = Sanitize::clean($this->data);
                $this->Candidate->create();
                if ($this->Candidate->save($dataToSave)) {
                    $dataToSave['CandidatesElection']['Election_id'] = $electionId;
                    $dataToSave['CandidatesElection']['Candidate_id'] = $this->Candidate->getInsertID();
                    $this->Candidate->CandidatesElection->create();
                    $this->Candidate->CandidatesElection->save($dataToSave);
                    $areaId = $this->Candidate->Election->AreasElection->field('Area_id', array('Election_id' => $electionId));
                    $this->Session->setFlash('資料已經儲存');
                    $this->redirect(array('controller' => 'areas', 'action' => 'index', $areaId));
                } else {
                    $this->Session->setFlash('資料儲存時發生錯誤，請重試');
                }
            }
            $parents = $this->Candidate->Election->getPath($electionId);
            $c = array();
            foreach ($parents AS $parent) {
                $c[] = $parent['Election']['name'];
            }
            $c[] = '新增候選人';
            $this->set('title_for_layout', implode(' > ', $c) . ' @ ');
            $this->set('electionId', $electionId);
            $this->set('referer', $this->request->referer());
            $this->set('parents', $parents);
        } else {
            $this->redirect(array('controller' => 'areas'));
        }
    }

    function edit($candidateId = '') {
        if (!empty($candidateId)) {
            $candidate = $this->Candidate->find('first', array(
                'conditions' => array(
                    'Candidate.id' => $candidateId,
                    'Candidate.active_id IS NULL',
                ),
                'contain' => array('Election'),
            ));
        }
        if (!empty($candidate)) {
            if (!empty($this->data)) {
                $dataToSave = Sanitize::clean($this->data);
                $dataToSave['Candidate']['active_id'] = $candidateId;
                $this->Candidate->create();
                if ($this->Candidate->save($dataToSave)) {
                    $dataToSave['CandidatesElection']['Election_id'] = $candidate['Election'][0]['id'];
                    $dataToSave['CandidatesElection']['Candidate_id'] = $this->Candidate->getInsertID();
                    $this->Candidate->CandidatesElection->create();
                    $this->Candidate->CandidatesElection->save($dataToSave);
                    $areaId = $this->Candidate->Election->AreasElection->field('Area_id', array('Election_id' => $candidate['Election'][0]['id']));
                    $this->Session->setFlash('感謝您提供的資料，我們會盡快更新！');
                    $this->redirect(array('controller' => 'areas', 'action' => 'index', $areaId));
                } else {
                    $this->Session->setFlash('資料儲存時發生錯誤，請重試');
                }
            } else {
                $candidate['CandidatesElection']['platform'] = str_replace('\\n', "\n", $candidate['Election'][0]['CandidatesElection']['platform']);
                $candidate['Candidate']['links'] = str_replace('\\n', "\n", $candidate['Candidate']['links']);
                $candidate['Candidate']['education'] = str_replace('\\n', "\n", $candidate['Candidate']['education']);
                $candidate['Candidate']['experience'] = str_replace('\\n', "\n", $candidate['Candidate']['experience']);
                $this->data = $candidate;
            }
            $parents = $this->Candidate->Election->getPath($candidate['Election'][0]['id']);
            $c = array();
            foreach ($parents AS $parent) {
                $c[] = $parent['Election']['name'];
            }
            $c[] = '更新候選人';
            $this->set('title_for_layout', implode(' > ', $c) . ' @ ');
            $this->set('candidateId', $candidateId);
            $this->set('referer', $this->request->referer());
            $this->set('parents', $parents);
        } else {
            $this->redirect(array('controller' => 'areas'));
        }
    }

    function view($id = null) {
        $this->data = $this->Candidate->find('first', array(
            'conditions' => array(
                'Candidate.id' => $id,
                'Candidate.active_id IS NULL',
            ),
            'contain' => array(
                'Election' => array(
                    'fields' => array('Election.id', 'Election.population_electors', 'Election.population'),
                    'Area' => array(
                        'fields' => array('Area.id', 'Area.name'),
                    ),
                ),
                'Tag' => array(
                    'fields' => array('Tag.id', 'Tag.name'),
                )
            ),
        ));
        if (!empty($this->data)) {
            $parents = $this->Candidate->Election->getPath($this->data['Election'][0]['id']);
            $desc_for_layout = '';
            $descElections = Set::extract('{n}.Election.name', $parents);
            if (!empty($descElections)) {
                $desc_for_layout .= $this->data['Candidate']['name'] . '在' . implode(' > ', $descElections) . '的參選資訊。';
            }
            $descElections[] = $this->data['Candidate']['name'];
            $this->set('referer', $this->request->referer());
            $this->set('desc_for_layout', $desc_for_layout);
            $this->set('title_for_layout', implode(' > ', $descElections) . '候選人 @ ');
            $this->set('parents', $parents);
        } else {
            $this->Session->setFlash('請依照網頁指示操作');
            $this->redirect(array('action' => 'index'));
        }
    }

    function admin_index($electionId = '') {
        $scope = array(
            'Candidate.active_id IS NULL',
        );
        if (!empty($electionId)) {
            $scope['CandidatesElection.Election_id'] = $electionId;
        }
        $this->paginate['Candidate']['joins'] = array(
            array(
                'table' => 'candidates_elections',
                'alias' => 'CandidatesElection',
                'type' => 'inner',
                'conditions' => array(
                    'CandidatesElection.Candidate_id = Candidate.id',
                ),
            ),
        );
        $this->paginate['Candidate']['limit'] = 20;
        $this->paginate['Candidate']['order'] = array(
            'Candidate.modified' => 'DESC',
        );
        $items = $this->paginate($this->Candidate, $scope);

        $this->set('items', $items);
        $this->set('electionId', $electionId);
        $this->set('url', array($electionId));
        $this->set('parents', $this->Candidate->Election->getPath($electionId));
    }

    function admin_view($id = null) {
        if (!empty($id)) {
            $this->data = $this->Candidate->find('first', array(
                'conditions' => array(
                    'Candidate.id' => $id,
                ),
                'contain' => array(
                    'Election' => array(
                        'fields' => array('Election.id', 'Election.population_electors', 'Election.population'),
                    ),
                ),
            ));
        }

        if (empty($this->data)) {
            $this->Session->setFlash('請依照網頁指示操作');
            $this->redirect(array('action' => 'index'));
        } else {
            if (!empty($this->data['Candidate']['active_id'])) {
                $targetId = $this->data['Candidate']['active_id'];
            } else {
                $targetId = $this->data['Candidate']['id'];
            }
            $versions = $this->Candidate->find('all', array(
                'conditions' => array('OR' => array(
                        'Candidate.id' => $targetId,
                        'Candidate.active_id' => $targetId,
                    )),
                'order' => array('Candidate.created DESC'),
            ));
            $this->set('versions', $versions);
        }
    }

    function admin_add($electionId = '') {
        if (!empty($electionId)) {
            if (!empty($this->data)) {
                $dataToSave = $this->data;
                $this->Candidate->create();
                if ($this->Candidate->save($dataToSave)) {
                    $dataToSave['CandidatesElection']['Election_id'] = $electionId;
                    $dataToSave['CandidatesElection']['Candidate_id'] = $this->Candidate->getInsertID();
                    $this->Candidate->CandidatesElection->create();
                    $this->Candidate->CandidatesElection->save($dataToSave);
                    $this->Session->setFlash('資料已經儲存');
                    $this->redirect(array('action' => 'index', $electionId));
                } else {
                    $this->Session->setFlash('資料儲存時發生錯誤，請重試');
                }
            }
            $this->set('electionId', $electionId);
        } else {
            $this->redirect(array('controller' => 'elections'));
        }
    }

    function admin_edit($id = null, $after = '') {
        if (!empty($id)) {
            $candidate = $this->Candidate->find('first', array(
                'conditions' => array(
                    'Candidate.id' => $id,
                ),
                'contain' => array('Election'),
            ));
        }
        if (!empty($candidate)) {
            if (!empty($this->data)) {
                $dataToSave = $this->data;
                $dataToSave['Candidate']['id'] = $id;
                if (!empty($dataToSave['Candidate']['image_upload']['size'])) {
                    $dataToSave['Candidate']['image'] = $dataToSave['Candidate']['image_upload'];
                }
                if ($this->Candidate->save($dataToSave)) {
                    $this->Session->setFlash('資料已經儲存');
                    if($after !== 'submits') {
                        $this->redirect(array('action' => 'index'));
                    } else {
                        $this->redirect(array('action' => 'submits'));
                    }
                } else {
                    $this->Session->setFlash('資料儲存時發生錯誤，請重試');
                }
            } else {
                $candidate['CandidatesElection']['platform'] = str_replace('\\n', "\n", $candidate['Election'][0]['CandidatesElection']['platform']);
                $candidate['Candidate']['links'] = str_replace('\\n', "\n", $candidate['Candidate']['links']);
                $candidate['Candidate']['education'] = str_replace('\\n', "\n", $candidate['Candidate']['education']);
                $candidate['Candidate']['experience'] = str_replace('\\n', "\n", $candidate['Candidate']['experience']);
                $this->set('id', $id);
                $this->data = $candidate;
            }
        } else {
            $this->Session->setFlash('請依照網頁指示操作');
            $this->redirect($this->referer());
        }
    }

    function admin_delete($id = null, $after = '') {
        if (!$id) {
            $this->Session->setFlash('請依照網頁指示操作');
        } else if ($this->Candidate->delete($id)) {
            $this->Session->setFlash('資料已經刪除');
        }
        if($after !== 'submits') {
            $this->redirect(array('action' => 'index'));
        } else {
            $this->redirect(array('action' => 'submits'));
        }
    }

    public function admin_submits() {
        $scope = array(
            'Candidate.active_id IS NOT NULL',
            'Candidate.is_reviewed' => '0',
        );
        $this->paginate['Candidate']['limit'] = 20;
        $this->paginate['Candidate']['order'] = array(
            'Candidate.created' => 'ASC',
        );
        $items = $this->paginate($this->Candidate, $scope);
        $this->set('items', $items);
    }

    public function admin_review($candidateId = '', $approved = '') {
        $fields = array('Candidate.id', 'Candidate.active_id', 'Candidate.name',
            'Candidate.image', 'Candidate.party', 'Candidate.contacts_phone',
            'Candidate.contacts_fax', 'Candidate.contacts_email',
            'Candidate.contacts_address', 'Candidate.links', 'Candidate.gender',
            'Candidate.birth', 'Candidate.education', 'Candidate.experience');
        $submitted = $this->Candidate->find('first', array(
            'fields' => $fields,
            'conditions' => array('id' => $candidateId),
            'contain' => array('Election' => array('fields' => array('Election.name'))),
        ));
        $original = $this->Candidate->find('first', array(
            'fields' => $fields,
            'conditions' => array('id' => $submitted['Candidate']['active_id']),
            'contain' => array('Election' => array('fields' => array('Election.name'))),
        ));
        $originalId = $original['Candidate']['id'];
        if ($approved === 'yes') {
            $dataToSave = array(
                'id' => $original['Candidate']['id'],
            );
            //update image
            if (!empty($submitted['Candidate']['image'])) {
                $dataToSave['image'] = $submitted['Candidate']['image'];
            }

            //update platform
            $this->Candidate->CandidatesElection->save(array('CandidatesElection' => array(
                    'id' => $original['Election'][0]['CandidatesElection']['id'],
                    'platform' => $submitted['Election'][0]['CandidatesElection']['platform'],
            )));

            //update candidate
            $cFields = array('name', 'party', 'contacts_phone', 'contacts_fax',
                'contacts_email', 'contacts_address', 'links', 'gender', 'birth',
                'education', 'experience');

            foreach ($cFields AS $cField) {
                $dataToSave[$cField] = $submitted['Candidate'][$cField];
            }
            $this->Candidate->save($dataToSave);
            $this->Candidate->id = $candidateId;
            $this->Candidate->saveField('is_reviewed', '1');
            $this->redirect('/admin/candidates/submits');
        } else {
            unset($submitted['Candidate']['id']);
            unset($original['Candidate']['id']);
            unset($submitted['Candidate']['active_id']);
            unset($original['Candidate']['active_id']);
            unset($submitted['Election'][0]['CandidatesElection']['id']);
            unset($original['Election'][0]['CandidatesElection']['id']);
            unset($submitted['Election'][0]['CandidatesElection']['Candidate_id']);
            unset($original['Election'][0]['CandidatesElection']['Candidate_id']);
            unset($submitted['Election'][0]['CandidatesElection']['Election_id']);
            unset($original['Election'][0]['CandidatesElection']['Election_id']);
        }
        $this->set('submitted', $submitted);
        $this->set('original', $original);
        $this->set('submittedId', $candidateId);
        $this->set('originalId', $originalId);
    }

}
