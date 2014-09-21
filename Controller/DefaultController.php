<?php

namespace Maci\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $admin = $this->getConfig();

        return $this->render('MaciAdminBundle:Default:index.html.twig', array(
            'admin' => $admin
        ));
    }

    public function navAction()
    {
        $list = $this->getConfig();

        return $this->render('MaciAdminBundle:Default:_nav.html.twig', array(
            'list' => $list
        ));
    }

    public function entityAction(Request $request, $entity)
    {
    	$admin = $this->getAdmin($entity);

        return $this->renderTemplate($request, $admin, 'entity', array(
            'admin' => $admin
        ));
    }

    public function formAction(Request $request, $entity, $id)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        $item = new $admin['new'];

        if ($id) {
            $item = $this->getDoctrine()->getManager()
                ->getRepository($admin['repository'])->findOneById($id);

            if (!$item) {
                return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
            }
        }

        $form = $this->createForm($admin['form'], $item);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($item);
            $em->flush();
            if ($request->isXmlHttpRequest()) {
                return $this->renderTemplate($request, $admin, 'item', array(
                    'admin' => $admin,
                    'item' => $item
                ));
            } else {
                return $this->redirect($this->generateUrl('maci_admin_entity_list', array(
                    'entity' => $admin['name'],
                    'message' => 'form.add'
                )));
            }
        }

        return $this->renderTemplate($request, $admin, 'form', array(
            'admin' => $admin,
            'item' => $item,
            'form' => $form->createView()
        ));
    }

    public function objectAction(Request $request, $entity, $id)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin || !$request->isXmlHttpRequest()) {
            return new JsonResponse(array('error' => 'error.noadmin'), 501);
        }

        $em = $this->getDoctrine()->getManager();

        $item = false;

        if ($id) {
            $item = $em->getRepository($admin['repository'])->findOneById($id);
            if (!$item) {
                return new JsonResponse(array('error' => 'error.noitem'), 501);
            }
        } else {
            $item = new $admin['new'];
        }

        $save = false;

        $fields = $request->get('fields');

        if (is_array($fields) && count($fields)) {
            foreach ($fields as $key => $value) {
                $mth = ( method_exists($item, $key) ?  $key : false );
                if ($mth) {
					call_user_method($mth, $item, $value);
					$save = true;
                }
            }
        }

        $relations = $request->get('relations');

        if (is_array($relations) && count($relations)) {
            foreach ($relations as $key => $value) {
                $mth = (
                    method_exists($item, ('set' . ucfirst($key))) || method_exists($item, ('add' . ucfirst($key))) ? (
                        method_exists($item, ('set' . ucfirst($key))) ?
                        'set' . ucfirst($key) :
                        'add' . ucfirst($key)
                    ) : false
                );
                if ($mth && $rel = $this->getAdmin($key)) {
					$rob = $em->getRepository($rel['repository'])->findOneById($value);
					if ($rob) {
					    call_user_method($mth, $item, $rob);
					    $save = true;
					}
                }
            }
        }

        if ($save) {
			if (method_exists($item, 'getTranslations')) {
			    $locs = $this->container->getParameter('a2lix_translation_form.locales');
			    foreach ($locs as $loc) {
			        $clnm = $admin['new'].'Translation';
			        $tran = new $clnm;
			        $tran->setLocale($loc);
			        $item->addTranslation($tran);
			        $em->persist($tran);
			    }
			}
            $em->persist($item);
            $em->flush();
        } else {
            return new JsonResponse(array('error' => 'error.nosave'), 501);
        }

        return $this->renderTemplate($request, $admin, 'item', array(
            'admin' => $admin,
            'item' => $item
        ));
    }

    public function itemAction(Request $request, $entity, $id)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        $item = $this->getDoctrine()->getManager()
            ->getRepository($admin['repository'])->findOneById($id);

        if (!$item) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        return $this->renderTemplate($request, $admin, 'item', array(
            'admin' => $admin,
            'item' => $item
        ));
    }

    public function listAction(Request $request, $entity)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        $repo = $this->getDoctrine()->getManager()->getRepository($admin['repository']);

        if ($filters = $request->get('filters')) {
            $item = new $admin['new'];
            $list = $repo->findBy($filters, (
                method_exists($item, 'setPosition') ?
                array('position' => 'ASC') :
                false
            ));
        } else {
            $list = $repo->findAll();
        }

        return $this->renderTemplate($request, $admin, 'list', array(
            'admin' => $admin,
            'list' => $list
        ));
    }

    public function removeAction(Request $request, $entity, $id)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin || !$request->isXmlHttpRequest()) {
            return new JsonResponse(array('error' => 'error.noadmin'), 501);
        }

        $em = $this->getDoctrine()->getManager();
        $item = $em->getRepository($admin['repository'])->findOneById($id);

        if (!$item) {
            return new JsonResponse(array('error' => 'error.not-found'), 501);
        }

        $em->remove($item);
        $em->flush();

        return new JsonResponse(array('result' => true), 200);
    }

    public function reorderAction(Request $request, $entity)
    {
        $admin = $this->getAdmin($entity);
        if (!$admin) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.notfound')));
        }

        $ids = $this->getRequest()->get('ids');

        $this->getDoctrine()->getRepository($admin['repository'])->reorder($ids);

        return new JsonResponse(array('result' => true), 200);
    }

    public function fileUploadAction(Request $request, $entity, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirect($this->generateUrl('maci_admin_homepage', array('error' => 'error.action_denied')));
        }

        $admin = $this->getAdmin($entity);

        if (!count($_FILES)) {
            return $this->renderTemplate($request, $admin, 'uploader', array());
        }
        if (!$admin) {
            return new JsonResponse(array('result' => 'false', 'error' => 'error.noadmin'), 501);
        }

        $em = $this->getDoctrine()->getManager();

        $repo = $em->getRepository($admin['repository']);

        $item = new $admin['new'];

        if ($id) {
            $item = $repo->findOneById($id);
            if (!$item) {
                return new JsonResponse(array('result' => 'false', 'error' => 'error.notfound'), 501);
            }
            $name = array_keys($_FILES)[0];
            $file = $_FILES[$name];
		    if($file['error'] == UPLOAD_ERR_OK and is_uploaded_file($file['tmp_name'])) {
		        $item->importFile( $file , $name );
		        $em->persist($item);
		    } else {
                return new JsonResponse(array('result' => 'false', 'error' => 'error.upload'), 501);
            }
        } else {
            $name = array_keys($_FILES)[0];
            $file = $_FILES[$name];
            if($file['error'] == UPLOAD_ERR_OK and is_uploaded_file($file['tmp_name'])) {
                if (method_exists($item, 'getTranslations')) {
                    $locs = $this->container->getParameter('a2lix_translation_form.locales');
                    $date = date('m/d/Y h:i:s');
                    foreach ($locs as $loc) {
                        $clnm = $admin['new'].'Translation';
                        $tran = new $clnm;
                        $tran->setLocale($loc);
                        $traname = 'Media [' . $loc . '] ' . $date;
                        $tran->setName($traname);
                        $item->addTranslation($tran);
                        $em->persist($tran);
                    }
                }
                $item->importFile( $file , $name );
                $em->persist($item);
            } else {
                return new JsonResponse(array('result' => 'false', 'error' => 'error.upload'), 501);
            }
        }
        $em->flush();

        return $this->renderTemplate($request, $admin, 'item', array(
            'admin' => $admin,
            'item' => $item
        ));
    }

    public function pagerAction()
    {
        $adminLimitPerPage = $this->getBlogConfig('admin_limit_per_page');
        $repo = $this->getDoctrine()->getEntityManager()->getRepository('Eight\BlogBundle\Entity\Post');
        $page = $this->getRequest()->get('page', 1);

        $pager = new PostPager($repo, $adminLimitPerPage, $page, 5);

        return $this->render('EightBlogBundle:Admin:list.html.twig', array(
            'posts' => $this->container->get('eight.posts')->getPaged($adminLimitPerPage, $page),
            'pager' => $pager
        ));
    }

    public function renderTemplate(Request $request, $admin, $action, $params)
    {
        if ( array_key_exists('templates', $admin) && array_key_exists($action, $admin['templates'])) {
            $template = $admin['templates'][$action];
        } else {
            $template = 'MaciAdminBundle:Default:_' . $action .'.html.twig';
        }

        if ($request->isXmlHttpRequest()) {
            $id = 0;
            if (array_key_exists('item', $params)) {
                $item = $params['item'];
                $id = $item->getId();
            }
            if ($request->get('modal')) {
                return new JsonResponse(array(
                    'success' => 'OK',
                    'id' => $id,
                    'entity' => $admin['name'],
                    'template' => $this->renderView('MaciAdminBundle:Default:async.html.twig', array(
                        'params' => $params,
                        'template' => $template
                    ))
                ), 200);
            } else {
                return new JsonResponse(array(
                    'success' => 'OK',
                    'id' => $id,
                    'entity' => $admin['name'],
                    'template' => $this->renderView($template, $params)
                ), 200);
            }
        } else {
            return $this->render('MaciAdminBundle:Default:' . $action .'.html.twig', array(
                'admin' => $admin,
                'params' => $params,
                'template' => $template
            ));
        }
    }

    public function getAdmin($entity = false)
    {
        $config = $this->getConfig();
        if ($entity && array_key_exists($entity, $config)) {
            $admin = $config[$entity];
            $admin['name'] = $entity;
            return $admin;
        }
        return false;
    }

    public function getConfig()
    {
        return array(
            'album' => array(
                'label' => 'Album',
                'repository' => 'MaciMediaBundle:Album',
                'new' => '\Maci\MediaBundle\Entity\Album',
                'form' => 'media_album',
                'menu' => true,
                'templates' => array(
                    'list' => 'MaciMediaBundle:Default:_list.html.twig',
                    'item' => 'MaciMediaBundle:Default:_item.html.twig'
                )
            ),
            'media' => array(
                'label' => 'Media',
                'repository' => 'MaciMediaBundle:Media',
                'new' => '\Maci\MediaBundle\Entity\Media',
                'form' => 'media',
                'menu' => true,
                'templates' => array(
                    'list' => 'MaciMediaBundle:Default:_list.html.twig',
                    'item' => 'MaciMediaBundle:Default:_item.html.twig'
                )
            ),
            'media_item' => array(
                'label' => 'Album Item',
                'repository' => 'MaciMediaBundle:Item',
                'new' => '\Maci\MediaBundle\Entity\Item',
                'form' => 'media_item',
                'templates' => array(
                    'list' => 'MaciMediaBundle:Default:_list.html.twig',
                    'item' => 'MaciMediaBundle:Default:_item.html.twig'
                )
            )
        );
    }
}
