<?php
declare(strict_types=1);

namespace Plan2net\Bapatren\Backend\Controller\Page;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Configuration\BackendUserConfiguration;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\Page\RootLineException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Class TreeController
 * @package Plan2net\Bapatren\Backend\Controller\Page
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class TreeController extends \TYPO3\CMS\Backend\Controller\Page\TreeController
{

    /**
     * Returns JSON representing all open nodes
     * of the page tree
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function fetchDataAction(ServerRequestInterface $request): ResponseInterface
    {
        $userTsConfig = $this->getBackendUser()->getTSConfig();
        $this->hiddenRecords = GeneralUtility::intExplode(',', $userTsConfig['options.']['hideRecords.']['pages'] ?? '',
            true);
        $this->backgroundColors = $userTsConfig['options.']['pageTree.']['backgroundColor.'] ?? [];
        $this->addIdAsPrefix = (bool)($userTsConfig['options.']['pageTree.']['showPageIdWithTitle'] ?? false);
        $this->addDomainName = (bool)($userTsConfig['options.']['pageTree.']['showDomainNameWithTitle'] ?? false);
        $this->showMountPathAboveMounts = (bool)($userTsConfig['options.']['pageTree.']['showPathAboveMounts'] ?? false);
        /** @var BackendUserConfiguration $backendUserConfiguration */
        $backendUserConfiguration = GeneralUtility::makeInstance(BackendUserConfiguration::class);
        $this->expandedState = $backendUserConfiguration->get('BackendComponents.States.Pagetree');
        if (is_object($this->expandedState) && is_object($this->expandedState->stateHash)) {
            $this->expandedState = (array)$this->expandedState->stateHash;
        } else {
            $this->expandedState = $this->expandedState['stateHash'] ?: [];
        }

        $items = [[]];
        // Fetching a part of a pagetree
        if (!empty($request->getQueryParams()['pid'])) {
            $backendUser = $this->getBackendUser();
            /** @var PageTreeRepository $repository */
            $repository = GeneralUtility::makeInstance(PageTreeRepository::class, (int)$backendUser->workspace);

            $entryPoint = $repository->getTree((int)$request->getQueryParams()['pid'],
                function ($page) use ($backendUser) {
                    // check each page if the user has permission to access it
                    return $backendUser->doesUserHaveAccess($page, Permission::PAGE_SHOW);
                });
            if (is_array($entryPoint)) {
                $entryPoint['expanded'] = true;
                $entryPoints = [
                    $entryPoint
                ];
            } else {
                $entryPoints = $this->getAllEntryPointPageTrees();
            }
        } else {
            $entryPoints = $this->getAllEntryPointPageTrees();
        }
        $depth = $request->getQueryParams()['depth'] ?? 0;
        foreach ($entryPoints as $page) {
            $items[] = $this->pagesToFlatArray($page, (int)$page['uid'], (int)$depth, [], (bool)($request->getQueryParams()['full'] ?? false));
        }

        return new JsonResponse(array_merge(...$items));
    }

    /**
     * Converts nested tree structure produced by PageTreeRepository to a flat, one level array
     * and also adds visual representation information to the data.
     *
     * @param array $page
     * @param int $entryPoint
     * @param int $depth
     * @param array $inheritedData
     * @param bool $full
     * @return array
     */
    protected function pagesToFlatArray(array $page, int $entryPoint, int $depth = 0, array $inheritedData = [], $full = false): array
    {
        $pageId = (int)$page['uid'];
        if (in_array($pageId, $this->hiddenRecords, true)) {
            return [];
        }

        $stopPageTree = !empty($page['php_tree_stop']) && $depth > 0;
        $identifier = $entryPoint . '_' . $pageId;
        $expanded = !empty($page['expanded']) ||
            (isset($this->expandedState[$identifier]) && $this->expandedState[$identifier]) ||
            ($entryPoint === $page['uid']);
        $backgroundColor = !empty($this->backgroundColors[$pageId]) ? $this->backgroundColors[$pageId] : ($inheritedData['backgroundColor'] ?? '');

        $suffix = '';
        $prefix = '';
        $nameSourceField = 'title';
        $visibleText = $page['title'];
        $tooltip = BackendUtility::titleAttribForPages($page, '', false);
        if ($pageId !== 0) {
            $icon = $this->iconFactory->getIconForRecord('pages', $page, Icon::SIZE_SMALL);
        } else {
            $icon = $this->iconFactory->getIcon('apps-pagetree-root', Icon::SIZE_SMALL);
        }

        if ($this->useNavTitle && trim($page['nav_title'] ?? '') !== '') {
            $nameSourceField = 'nav_title';
            $visibleText = $page['nav_title'];
        }
        if (trim($visibleText) === '') {
            $visibleText = htmlspecialchars('[' . $GLOBALS['LANG']->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.no_title') . ']');
        }
        $visibleText = GeneralUtility::fixed_lgd_cs($visibleText, (int)$this->getBackendUser()->uc['titleLen'] ?: 40);

        if ($this->addDomainName && $page['is_siteroot']) {
            $domain = $this->getDomainNameForPage($pageId);
            $suffix = $domain !== '' ? ' [' . $domain . ']' : '';
        }

        $lockInfo = BackendUtility::isRecordLocked('pages', $pageId);
        if (is_array($lockInfo)) {
            $tooltip .= ' - ' . $lockInfo['msg'];
        }
        if ($this->addIdAsPrefix) {
            $prefix = htmlspecialchars('[' . $pageId . '] ');
        }

        $items = [[]];
        $items[] = [
            [
                // Used to track if the tree item is collapsed or not
                'stateIdentifier' => $identifier,
                'identifier' => $pageId,
                'depth' => $depth,
                'tip' => htmlspecialchars($tooltip),
                'hasChildren' => !empty($page['_children']),
                'icon' => $icon->getIdentifier(),
                'name' => $visibleText,
                'nameSourceField' => $nameSourceField,
                'alias' => htmlspecialchars($page['alias'] ?? ''),
                'prefix' => htmlspecialchars($prefix),
                'suffix' => htmlspecialchars($suffix),
                'locked' => is_array($lockInfo),
                'overlayIcon' => $icon->getOverlayIcon() ? $icon->getOverlayIcon()->getIdentifier() : '',
                'selectable' => true,
                'expanded' => (bool)$expanded,
                'checked' => false,
                'backgroundColor' => htmlspecialchars($backgroundColor),
                'stopPageTree' => $stopPageTree,
                'class' => $this->resolvePageCssClassNames($page),
                'readableRootline' => $depth === 0 && $this->showMountPathAboveMounts ? $this->getMountPointPath($pageId) : '',
                'isMountPoint' => $depth === 0,
                'mountPoint' => $entryPoint,
                'workspaceId' => !empty($page['t3ver_oid']) ? $page['t3ver_oid'] : $pageId,
            ]
        ];
        if (!$stopPageTree && ($expanded || $full)) {
            foreach ($page['_children'] as $child) {
                $items[] = $this->pagesToFlatArray(
                    $child,
                    $entryPoint,
                    $depth + 1,
                    ['backgroundColor' => $backgroundColor],
                    $full
                );
            }
        }
        return array_merge(...$items);
    }

    /**
     * Fetches all entry points for the page tree that the user is allowed to see
     *
     * plan2net: remove the check for PAGE_SHOW
     *
     * @return array
     */
    protected function getAllEntryPointPageTrees(): array
    {
        $backendUser = $this->getBackendUser();
        $repository = GeneralUtility::makeInstance(PageTreeRepository::class, (int)$backendUser->workspace);

        $entryPoints = (int)($backendUser->uc['pageTree_temporaryMountPoint'] ?? 0);
        if ($entryPoints > 0) {
            $entryPoints = [$entryPoints];
        } else {
            $entryPoints = array_map('intval', $backendUser->returnWebmounts());
            $entryPoints = array_unique($entryPoints);
            if (empty($entryPoints)) {
                // use a virtual root
                // the real mount points will be fetched in getNodes() then
                // since those will be the "sub pages" of the virtual root
                $entryPoints = [0];
            }
        }
        if (empty($entryPoints)) {
            return [];
        }

        foreach ($entryPoints as $k => &$entryPoint) {
            if (in_array($entryPoint, $this->hiddenRecords, true)) {
                unset($entryPoints[$k]);
                continue;
            }

            if (!empty($this->backgroundColors) && is_array($this->backgroundColors)) {
                try {
                    $entryPointRootLine = GeneralUtility::makeInstance(RootlineUtility::class, $entryPoint)->get();
                } catch (RootLineException $e) {
                    $entryPointRootLine = [];
                }
                foreach ($entryPointRootLine as $rootLineEntry) {
                    $parentUid = $rootLineEntry['uid'];
                    if (!empty($this->backgroundColors[$parentUid]) && empty($this->backgroundColors[$entryPoint])) {
                        $this->backgroundColors[$entryPoint] = $this->backgroundColors[$parentUid];
                    }
                }
            }

            // plan2net: remove check for PAGE_SHOW for every single page
            $entryPoint = $repository->getTree($entryPoint);
            if (!is_array($entryPoint)) {
                unset($entryPoints[$k]);
            }
        }

        return $entryPoints;
    }

}
