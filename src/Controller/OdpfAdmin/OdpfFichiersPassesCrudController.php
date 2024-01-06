<?php

namespace App\Controller\OdpfAdmin;

use App\Controller\Admin\Filter\CustomEditionspasseesFilter;
use App\Controller\Admin\Filter\CustomEquipespasseesFilter;
use App\Entity\Fichiersequipes;
use App\Entity\Odpf\OdpfEditionsPassees;
use App\Entity\Odpf\OdpfEquipesPassees;
use App\Entity\Odpf\OdpfFichierspasses;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Vich\UploaderBundle\Form\Type\VichFileType;

class OdpfFichiersPassesCrudController extends AbstractCrudController
{
    private AdminContextProvider $adminContextProvider;
    private ManagerRegistry $doctrine;
    private RequestStack $requestStack;

    public function __construct(AdminContextProvider $adminContextProvider, ManagerRegistry $doctrine, RequestStack $requestack)
    {
        $this->adminContextProvider = $adminContextProvider;
        $this->doctrine = $doctrine;
        $this->requestStack = $requestack;
    }

    public static function getEntityFqcn(): string
    {
        return OdpfFichierspasses::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->showEntityActionsInlined();
        return parent::configureCrud($crud); // TODO: Change the autogenerated stub
    }

    public function configureActions(Actions $actions): Actions
    {
        $setPublie = Action::new('setPublie', 'Publier les fichiers')
            ->linkToRoute('setPublie')
            ->createAsGlobalAction();

        $telechargerUnFichierOdpf = Action::new('telechargerunfichierOdpf', 'Télécharger', 'fa fa-file-download')
            ->linkToRoute('telechargerUnFichierOdpf', function (OdpfFichierspasses $fichier): array {
                return [
                    'idEntity' => $fichier->getId(),
                ];
            });

        $actions->add(Crud::PAGE_INDEX, $telechargerUnFichierOdpf)
            ->add(Crud::PAGE_INDEX, $setPublie)
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
        return parent::configureActions($actions); // TODO: Change the autogenerated stub
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(CustomEditionspasseesFilter::new('editionspassees', 'edition'))
            ->add(CustomEquipespasseesFilter::new('equipespassees', 'equipe'));


    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $context = $this->adminContextProvider->getContext();
        $typefichier = $context->getRequest()->query->get('typefichier');
        $qb = $this->doctrine->getRepository(OdpfFichierspasses::class)->createQueryBuilder('f');

        if (($typefichier == 0) or ($typefichier == 1)) {
            $qb->andWhere('f.typefichier <=:type')
                ->setParameter('type', 1);
        } else {
            $qb->andWhere('f.typefichier =:type')
                ->setParameter('type', $typefichier);

        }
        $qb->leftJoin('f.equipepassee', 'eq')
            ->leftJoin('f.editionspassees', 'ed')
            ->addOrderBy('ed.edition', 'DESC')
            ->addOrderBy('eq.numero', 'ASC');

        if (isset($_REQUEST['filters'])) {

            if (isset($_REQUEST['filters']['editionspassees'])) {
                $qb->andWhere('f.editionspassees =:edition')
                    ->setParameter('edition', $this->doctrine->getRepository(OdpfEditionspassees::class)->findOneBy(['id' => $_REQUEST['filters']['editionspassees']]));
            }
            if (isset($_REQUEST['filters']['equipespassees'])) {
                $qb->andWhere('f.equipepassee =:equipe')
                    ->setParameter('equipe', $this->doctrine->getRepository(OdpfEquipespassees::class)->findOneBy(['id' => $_REQUEST['filters']['equipespassees']]));
            }
        }


        return $qb;
    }

    public function configureFields(string $pageName): iterable

    {

        $repositoryEdition = $this->doctrine->getRepository(OdpfEquipesPassees::class);
        if ($_REQUEST['crudAction'] == 'edit') {
            $fichier = $this->doctrine->getRepository(OdpfFichierspasses::class)->find(['id' => $_REQUEST['entityId']]);
            $numtypefichier = $fichier->getTypefichier();

        } else {
            $numtypefichier = $_REQUEST['typefichier'];
        }
        if ($numtypefichier == 1) {
            $numtypefichier = 0;
        }
        if ($pageName == Crud::PAGE_NEW) {

            $panel1 = FormField::addPanel('<p style= "color :red" > Déposer un nouveau ' . $this->getParameter('type_fichier_lit')[$numtypefichier] . '  </p> ');


        }
        if ($pageName == Crud::PAGE_EDIT) {

            $panel1 = FormField::addPanel('<p style= "color:red" > Editer le fichier ' . $this->getParameter('type_fichier_lit')[$numtypefichier] . '  </p> ');


        }

        $equipe = AssociationField::new('equipepassee')->setFormTypeOptions(['data_class' => null])
            ->setQueryBuilder(function ($queryBuilder) {

                return $queryBuilder->select()->addOrderBy('entity.editionspassees', 'DESC')
                    ->addOrderBy('entity.lettre', 'ASC')
                    ->addOrderBy('entity.numero', 'ASC');
            }
            );
        $fichierFile = Field::new('fichierFile', 'fichier')
            ->setFormType(VichFileType::class)
            ->setLabel('Fichier')
            ->onlyOnForms()
            ->setFormTypeOption('allow_delete', false);//sinon la case à cocher delete s'affiche
        //$numtypefichier=$this->set_type_fichier($_REQUEST['menuIndex'],$_REQUEST['submenuIndex']);
        switch ($numtypefichier) {
            case 7:
            case 2:
            case 3:
            case 5:
            case 0 :
                $article = 'le';
                break;
            case 6:
            case 1 :
                $article = 'l\'';
                break;
            case 4 :
                $article = 'la';
                break;
        }

        $panel2 = FormField::addPanel('<p style=" color:red" > Modifier ' . $article . ' ' . $this->getParameter('type_fichier_lit')[$numtypefichier] . '</p> ');
        $id = IntegerField::new('id', 'ID');
        $fichier = TextField::new('nomfichier');//->setTemplatePath('bundles\\EasyAdminBundle\\liste_fichiers.html.twig');
        $publie = BooleanField::new('publie')->renderAsSwitch(false);

        //$typefichier = IntegerField::new('typefichier');

        if ($pageName == Crud::PAGE_INDEX) {
            $context = $this->adminContextProvider->getContext();
            $context->getRequest()->query->set('typefichier', $numtypefichier);
        }
        $annexe = ChoiceField::new('typefichier', 'Mémoire ou annexe')
            ->setChoices(['Memoire' => 0, 'Annexe' => 1])
            ->setFormTypeOptions(['required' => true])
            ->setColumns('col-sm-4 col-lg-3 col-xxl-2');
        $updatedAt = DateTimeField::new('updatedAt');
        $edition = AssociationField::new('editionpassee');
        $national = BooleanField::new('national');
        $editionEd = IntegerField::new('editionspassees.edition');

        $equipeNumero = AssociationField::new('equipepassee', 'N° équipe')->setFormTypeOptions(
            ['data_class' => OdpfEquipesPassees::class,
                'choices_label' => 'getNumero']);
        $equipeLettre = AssociationField::new('equipepassee', 'Lettre équipe')->setFormTypeOptions(
            ['data_class' => OdpfEquipesPassees::class,
                'choice_label' => function (OdpfEquipesPassees $equipepassee = null) {
                    if ($equipepassee !== null) {
                        return $equipepassee->getId();
                    } else {
                        return '';
                    }

                }]);
        $equipeTitreprojet = AssociationField::new('equipepassee', 'Projet')->setFormTypeOptions(
            [
                'class' => OdpfEquipesPassees::class,
                'choices_label' => 'getTitreprojet']);

        $updatedat = DateTimeField::new('updatedat', 'Déposé le ');

        if (Crud::PAGE_INDEX === $pageName) {

            return [$editionEd, $equipeTitreprojet, $fichier, $publie, $updatedat];

        }
        if (Crud::PAGE_DETAIL === $pageName) {
            return [$edition, $equipe, $fichier, $publie, $updatedAt, $national];
        }
        if (Crud::PAGE_NEW === $pageName) {


            if ($numtypefichier == 0) {
                return [$panel1, $equipe, $fichierFile, $annexe, $publie, $national];
            }
            if (($numtypefichier == 2) or ($numtypefichier == 3)) {
                return [$panel1, $equipe, $fichierFile, $publie, $national];
            }
            if (($numtypefichier == 4) or ($numtypefichier == 5)) {

                return [$panel1, $equipe, $fichierFile];
            }
            if ($numtypefichier == 6) {

                return [$panel1, $fichierFile];
            }
        }
        if (Crud::PAGE_EDIT === $pageName) {
            if ($numtypefichier == 0) {
                return [$panel1, $equipe, $fichierFile, $annexe, $publie, $national];
            }
            if (($numtypefichier == 2) or ($numtypefichier == 3)) {
                return [$panel1, $equipe, $fichierFile, $publie, $national];
            }
            if (($numtypefichier == 4) or ($numtypefichier == 5)) {
                return [$panel1, $equipe, $fichierFile];
            }
            if ($numtypefichier == 6) {

                return [$panel1, $equipe, $fichierFile];
            }
        }
    }

    public function set_type_fichier($valueIndex, $valueSubIndex): int
    {
        if ($valueIndex == 6) {
            switch ($valueSubIndex) {
                case 2 :
                    $typeFichier = 0; //mémoires ou annexes 1
                    break;
                case 3:
                    $typeFichier = 2;  //résumés
                    break;

                case 4 :
                    $typeFichier = 3; //Présentations
                    break;

                case 5:
                    $typeFichier = 6; //Autorisations photos
                    break;
            }
        }

        return $typeFichier;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {

        if (($entityInstance->getTypefichier() < 4)) {
            $this->fichierspublies($entityInstance);
            parent::updateEntity($entityManager, $entityInstance); // TODO: Change the autogenerated stub
        }


        parent::updateEntity($entityManager, $entityInstance); // TODO: Change the autogenerated stub
    }

    #[Route("/Admin/FichiersequipesCrud/telechargerUnFichierOdpf", name: "telechargerUnFichierOdpf")]
    public function telechargerUnFichierOdpf(AdminContext $context)
    {

        $idFichier = $_REQUEST['routeParams']['idEntity'];
        $fichier = $this->doctrine->getRepository(OdpfFichierspasses::class)->findOneBy(['id' => $idFichier]);
        $edition = $fichier->getEditionspassees();
        $typefichier = $fichier->getTypefichier();
        $chemintypefichier = $this->getParameter('type_fichier')[$typefichier].'/';
        if ($typefichier == 1) {
            $chemintypefichier = $this->getParameter('type_fichier')[0].'/';
        }
        if ($typefichier < 4) {
            $fichier->getPublie() == true ? $acces = $this->getParameter('fichier_acces')[1] : $acces = $this->getParameter('fichier_acces')[0];
            $chemintypefichier = $chemintypefichier . '/' . $acces . '/';
        }
        $file = $this->getParameter('app.path.odpf_archives') . '/' . $edition->getEdition() . '/fichiers/' . $chemintypefichier . $fichier->getNomfichier();
       /* header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=' . $fichier->getNomfichier());
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
        header('Cache-Control: private', false);
        header('Pragma: no-cache');
        header('Content-Length: ' . filesize($file));
        readfile($file);*/
        $response = new Response(file_get_contents($file));

        $type=mime_content_type($file);
        if (str_contains($_SERVER['HTTP_USER_AGENT'], 'iPad') or str_contains($_SERVER['HTTP_USER_AGENT'], 'Mac OS X')) {
            $response = new BinaryFileResponse($file);

        }
        $response->headers->set('Content-Disposition: attachment','attachment; filename="'. $fichier->getNomFichier().'"' );
        $response->headers->set('Content-Description', 'File Transfer');
        $response->headers->set('Content-type',$type);
        $response->headers->set('Content-Length', filesize($file));

        return $response;

    }

    public function fichierspublies($fichier)//transfert le fichier dans le dossier prive ou le dossier publie selon l'update réalisée
    {

        $publie = $fichier->getPublie();
        $fichierName = $fichier->getNomfichier();
        $fichier->getTypefichier() == 1 ? $numtypeFichier = 0 : $numtypeFichier = $fichier->getTypefichier();
        //dd('opdf/odpf-archives/' . $fichier->getEdition()->getEd() . '/fichiers/' . $this->getParameter('type_fichier')[$fichier->getTypefichier()] . '/prive/' . $fichierName);
        $path = $this->getParameter('app.path.odpf_archives') . '/';

        if ($publie == true) {
            if (!file_exists($path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/publie')) {
                mkdir($path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/publie');
            }
            if (!file_exists($path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/publie/' . $fichierName)) {
                rename($path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/prive/' . $fichierName, $path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/publie/' . $fichierName);
            }

        }
        if (($publie == false) or ($publie == null)) {
            if (!file_exists($path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/prive')) {
                mkdir($path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/prive');
            }
            if (!file_exists($path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/prive/' . $fichierName)) {
                rename($path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/publie/' . $fichierName, $path . $fichier->getEditionspassees()->getEdition() . '/fichiers/' . $this->getParameter('type_fichier')[$numtypeFichier] . '/prive/' . $fichierName);
            }

        }

    }

}