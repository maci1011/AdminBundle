<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ViewController extends Controller
{
    public function indexAction(Request $request)
    {
        return $this->redirect($this->generateUrl('maci_admin_view'));
    }

    public function viewAction(Request $request, $section, $entity, $action, $id)
    {
        $admin = $this->container->get('maci.admin');
        $sections = $admin->getAuthSections();

        if (!count($sections)) {
            return $this->redirect($this->generateUrl('homepage'));
        }
        if (!$section) {
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$sections[0])));
        }
        if (!in_array($section, $sections)) {
            $request->getSession()->getFlashBag()->add('error', 'Section not Found.');
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$sections[0])));
        }
        if (!$entity) {
            if ($admin->hasSectionDashboard($section)) {
                return $this->render($admin->getSectionDashboard($section));
            }
            $entities = array_keys($admin->getEntities($section));
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entities[0],'action'=>'list')));
        }
        if (!$admin->hasEntity($section, $entity)) {
            $request->getSession()->getFlashBag()->add('error', 'Entity not Found.');
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section)));
        }
        if (!$admin->hasAction($section, $entity, $action)) {
            $request->getSession()->getFlashBag()->add('error', 'Action not Found.');
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>'list')));
        }

        if ($action === 'relations') {
            if (!intval($id)) {
                $request->getSession()->getFlashBag()->add('error', 'Missing Id.');
                return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>'list')));
            }
            $relation = $request->get('relation');
            if (!$relation) {
                $_entity = $admin->getEntity($section, $entity);
                $relations = $admin->getAssociations($_entity);
                $relAction = $admin->getRelationDefaultAction($_entity, $relation[0]);
                return $this->redirect($this->generateUrl('maci_admin_view_relations', array('section'=>$section,'entity'=>$entity,'action'=>$action,'id'=>$id,'relation'=>$relations[0],'relAction'=>$relAction)));
            }
            $relAction = $request->get('relAction');
            if (!$relAction || !in_array($relAction, $admin->getRelationActions($section,$entity,$relation))) {
                $_entity = $admin->getEntity($section, $entity);
                $relAction = $admin->getRelationDefaultAction($_entity, $relation);
                return $this->redirect($this->generateUrl('maci_admin_view_relations', array('section'=>$section,'entity'=>$entity,'action'=>$action,'id'=>$id,'relation'=>$relation,'relAction'=>$relAction)));
            }
        }

        $controller = $this->container->get('maci.admin.default');

        $callAction = ( $action . 'Action' );
        if (!method_exists($controller, $callAction)) {
            $request->getSession()->getFlashBag()->add('error', 'View [' . $callAction . '] not found in [' . get_class($controller) . '].');
            return $this->redirect($this->generateUrl('maci_admin_not_found'));
        }

        $params = call_user_method($callAction, $controller, $section, $entity, $id);

        if ($params===false) {
            $request->getSession()->getFlashBag()->add('error', 'Something wrong. :(');
            return $this->redirect($this->generateUrl('maci_admin_not_found'));
        }

        if (array_key_exists('redirect', $params)) {
            return $this->redirect($this->generateUrl($params['redirect'],$params['redirect_params']));
        }

        if ($request->isXmlHttpRequest()) {
            if ($request->getMethod() === 'POST') {
                return new JsonResponse($params, 200);
            } else {
                $render = $this->renderView($params['template'], array(
                    'params' => $params
                ));
                $json_params = array_merge($params, array('html' => $render));
                return new JsonResponse($json_params, 200);
            }
        }

        return $this->render('MaciAdminBundle:Default:view.html.twig', array(
            'template' => $params['template'],
            'params' => $params
        ));
    }

    public function adminBarAction(Request $request, $entity, $item = false)
    {
        $admin = $this->container->get('maci.admin');
        $sections = $admin->getAuthSections();
        $id = false;
        $section = false;
        $actions = false;

        foreach ($sections as $secname) {
            if ($admin->hasEntity($secname, $entity)) {
                $section = $secname;
                if ($item) {
                    $actions = $admin->arrayLabels($admin->getSingleActions($section,$entity));
                    $id = $item->getId();
                } else {
                    $actions = $admin->arrayLabels($admin->getMainActions($section,$entity));
                }
                break;
            }
        }

        return $this->render('MaciAdminBundle:Default:admin_bar.html.twig', array(
            'id' => $id,
            'section' => $section,
            'entity' => $entity,
            'entity_label' => $admin->generateLabel($entity),
            'item' => $item,
            'actions' => $actions
        ));
    }

    public function notFoundAction(Request $request)
    {
        return $this->render('MaciAdminBundle:Default:not_found.html.twig');
    }
}
