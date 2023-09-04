<?php

namespace App\Command;

use App\Entity\Permission;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class UpdatePermissionsCommand
 * @package App\Command
 */
class UpdatePermissionsCommand extends Command
{
    protected static $defaultName = 'app:update-permissions';

    private EntityManagerInterface $manager;
    private RouterInterface $router;
    private PermissionRepository $permissionRepository;
    private RoleRepository $roleRepository;

    /**
     * AddCategoriesToPostsCommand constructor.
     * @param EntityManagerInterface $manager
     * @param RouterInterface $router
     * @param PermissionRepository $permissionRepository
     * @param RoleRepository $roleRepository
     */
    public function __construct(
        EntityManagerInterface $manager,
        RouterInterface $router,
        PermissionRepository $permissionRepository,
        RoleRepository $roleRepository
    )
    {
        $this->manager = $manager;
        $this->router = $router;
        $this->permissionRepository = $permissionRepository;
        $this->roleRepository = $roleRepository;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('This command update permissions for admin roles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->text('Updating permissions...');

            $routeCollection = $this->router->getRouteCollection();

            $routes = [];

            foreach ($routeCollection as $name => $route) {
                if(preg_match('/^\/api\/admin(.)*/',$route->getPath())) {
                    $routes[] = [
                        'name' => $name,
                        'path' => $route->getPath(),
                    ];
                }
            }

            $permissions = $this->permissionRepository->findAll();

            $dbPermissions = [];
            if (count($permissions) > 0) {
                foreach ($permissions as $permission) {
                    $dbPermissions[] = $permission->getRoute();
                }
            }

            $superAdminRole = $this->roleRepository->findOneBy(['title' => Role::ROLE_SUPER_ADMIN]);

            if (!$superAdminRole) {
                $superAdminRole = new Role();
                $superAdminRole->setTitle(Role::ROLE_SUPER_ADMIN);

                $this->manager->persist($superAdminRole);
            }

            if (!in_array(Permission::SWITCH_USER, $dbPermissions)) {
                $permission = new Permission();
                $permission->setTitle(Permission::SWITCH_USER);
                $permission->setRouteName(Permission::SWITCH_USER);
                $permission->setRoute(Permission::SWITCH_USER);
                $permission->addRole($superAdminRole);

                $this->manager->persist($permission);
            }

            foreach ($routes as $route) {
                if (!in_array($route['path'], $dbPermissions)) {
                    $permission = new Permission();
                    $permission->setTitle($route['name']);
                    $permission->setRouteName($route['name']);
                    $permission->setRoute($route['path']);
                    $permission->setCreatedAt(new \DateTimeImmutable());
                    $permission->addRole($superAdminRole);

                    $this->manager->persist($permission);
                }
            }

            $this->manager->flush();

            $io->text('Permissions was updated!');

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $io->text($exception->getMessage());
            return Command::FAILURE;
        }
    }

}
