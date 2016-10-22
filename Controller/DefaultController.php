<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends Controller
{
    public function indexAction(Request $request)
    {
        return $this->redirect($this->generateUrl('maci_admin_view'));
    }

    public function notFoundAction(Request $request)
    {
        // var_dump( $this->getDoctrine()->getManager()->getMetadataFactory()->getAllMetadata() ); die();
        // var_dump( $this->container->getParameter('maci.admin.config') ); die();
        // foreach ($this->get('kernel')->getBundles() as $key => $value) {
        //     echo get_class($value)."<br>\n";
        // } die();
        // var_dump( $this->container->get('maci.admin')->getEntityMetadata($this->container->get('maci.admin')->getEntity('blog_post'))); die();
        // var_dump( $this->getDoctrine()->getManager()->getConfiguration()->getMetadataDriverImpl()->getAllClassNames() ); die();
        // var_dump( $this->getDoctrine()->getManager()->getClassMetadata('Maci\BlogBundle\Entity\Post') ); die();
        // var_dump( $this->container->get('maci.admin')->getSections() ); die();
        // var_dump( get_class($this->container->get('router')) ); die();
        return $this->render('MaciAdminBundle:Default:not_found.html.twig');
    }

    public function viewAction(Request $request, $section, $entity, $action, $id)
    {
        $admin = $this->container->get('maci.admin');
        $sections = $admin->getSections();

        if (!count($sections)) {
            return $this->redirect($this->generateUrl('homepage'));
        }
        if (!$section || !in_array($section, $sections)) {
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$sections[0])));
        }
        if (!$entity) {
            return $this->render('MaciAdminBundle:Default:dashboard.html.twig', array(
                'section' => $section,
                'section_label' => $admin->getSectionLabel($section)
            ));
        }
        if (!$admin->hasEntity($section, $entity)) {
            return $this->redirect($this->generateUrl('maci_admin_not_found'));
        }
        if (!$action || !in_array($action, $admin->getActions($section, $entity))) {
            return $this->redirect($this->generateUrl('maci_admin_view', array('section'=>$section,'entity'=>$entity,'action'=>'list')));
        }
        if ($action === 'relations') {
            $relation = $request->get('relation');
            if (!$relation) {
                $_entity = $admin->getEntity($section, $entity);
                $relations = $admin->getEntityAssociations($_entity);
                return $this->redirect($this->generateUrl('maci_admin_view_relations', array('section'=>$section,'entity'=>$entity,'action'=>$action,'id'=>$id,'relation'=>$relations[0])));
            }
            $relAction = $request->get('relAction');
            if (!$relAction) {
                $_entity = $admin->getEntity($section, $entity);
                $relAction = $admin->getEntityRelationDefaultAction($_entity, $relation);
                return $this->redirect($this->generateUrl('maci_admin_view_relations', array('section'=>$section,'entity'=>$entity,'action'=>$action,'id'=>$id,'relation'=>$relation,'relAction'=>$relAction)));
            }
        }

        $callAction = ( $action . 'Action' );
        if (!method_exists($admin, $callAction)) {
            return $this->redirect($this->generateUrl('maci_admin_not_found'));
        }

        $params = call_user_method($callAction, $admin, $section, $entity, $id);

        if ($params===false) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(array('error' => true), 200);
            }
            return $this->redirect($this->generateUrl('maci_admin_not_found'));
        }

        if (array_key_exists('redirect', $params)) {
            return $this->redirect($this->generateUrl($params['redirect'],$params['redirect_params']));
        }

        $template = 'MaciAdminBundle:Actions:' . $action .'.html.twig';

        return $this->render('MaciAdminBundle:Default:view.html.twig', array(
            'template' => $template,
            'params' => array_merge($admin->getDefaultParams($request, $section, $entity, $action, $id), $params)
        ));
    }

    public function adminBarAction(Request $request, $entity, $item = false)
    {
        $admin = $this->container->get('maci.admin');
        $sections = $admin->getSections();
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
            'item' => $item,
            'actions' => $actions
        ));
    }
}
