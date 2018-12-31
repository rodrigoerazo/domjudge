<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use DOMJudgeBundle\Entity\Testcase;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_ADMIN')")
 */
class AuditLogController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * AuditLogController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("/auditlog/", name="jury_auditlog")
     */
    public function indexAction(Request $request, Packages $assetPackage, KernelInterface $kernel)
    {
        $timeFormat = (string)$this->DOMJudgeService->dbconfig_get('time_format', '%H:%M');

        $page = $request->query->get('page', 1);
        $limit = 1000;

        $em = $this->entityManager;
        $query = $em->createQueryBuilder()
                    ->select('a')
                    ->from('DOMJudgeBundle:AuditLog', 'a')
                    ->orderBy('a.logid', 'DESC');

        $paginator = new Paginator($query);
        $paginator->getQuery()
                  ->setFirstResult($limit * ($page - 1))
                  ->setMaxResults($limit);

        $auditlog_table= [];
        foreach($paginator as $logline) {

            $data = [];
            $data['id']['value'] = $logline->getLogId();

            $time = $logline->getLogTime();
            $data['when']['value'] = Utils::printtime($time, $timeFormat);
            $data['when']['title'] = Utils::printtime($time, "%Y-%m-%d %H:%M:%S (%Z)");
            $data['when']['sortvalue'] = $time;

            $data['who']['value'] = $logline->getUser();

            $datatype = $logline->getDatatype();
            $dataid = $logline->getDataId();
            $data['what']['value'] = $datatype . " " . $dataid . " " .
                            $logline->getAction() . " " .
                            $logline->getExtraInfo();
            if( !is_null($dataid) ) {
                $dataurl = $this->generateDatatypeUrl($datatype, $dataid);
            }
            if ( $dataurl ) {
                $data['what']['link'] = $dataurl;
            }

            $cid = $logline->getCid();
            if ( $cid ) {
                    $data['where']['value'] = "c" . $cid;
                    $data['where']['sortvalue'] = $cid;
                    $data['where']['link'] = $this->generateUrl('jury_contest', ['contestId' => $cid]);
            } else {
                    $data['where']['value'] = '';
            }

            $auditlog_table[] = [ 'data' => $data, 'actions' => [] ];
        }
        $table_fields = [
            'id' => ['title' => 'ID', 'sort' => false],
            'when' => ['title' => 'time', 'sort' => false],
            'who' => ['title' => 'user', 'sort' => false],
            'where' => ['title' => 'contest', 'sort' => false],
            'what' => ['title' => 'action', 'sort' => false],
        ];

        $maxPages = ceil($paginator->count() / $limit);
        $thisPage = $page;

        return $this->render('@DOMJudge/jury/auditlog.html.twig', [
            'auditlog' => $auditlog_table,
            'table_fields' => $table_fields,
            'table_options' => ['ordering' => 'false', 'searching' => 'false'],
            'maxPages' => $maxPages,
            'thisPage' => $thisPage
        ]);
    }

    private function generateDatatypeUrl(string $type, $id)
    {
        switch ($type) {
            case 'balloon':
                return $this->generateUrl('jury_balloons');
            case 'clarification':
                return $this->generateUrl('jury_clarification', ['id' => $id]);
            case 'configuration':
                return $this->generateUrl('jury_config');
            case 'contest':
                return $this->generateUrl('jury_contest', ['contestId' => $id]);
            case 'executable':
                return $this->generateUrl('jury_executable', ['execId' => $id]);
            case 'internal_error':
                return $this->generateUrl('jury_internal_error', ['errorId' => $id]);
            case 'judgehost':
                return $this->generateUrl('jury_judgehost', ['hostname' => $id]);
            case 'judgehosts':
                return $this->generateUrl('jury_judgehosts');
            case 'judgehost_restriction':
                return $this->generateUrl('jury_judgehost_restriction', ['restrictionId' => $id]);
            case 'judging':
                return $this->generateUrl('jury_submission_by_judging', ['jid' => $id]);
            case 'language':
                return $this->generateUrl('jury_language', ['langId' => $id]);
            case 'problem':
                return $this->generateUrl('jury_problem', ['probId' => $id]);
            case 'submission':
                return $this->generateUrl('jury_submission', ['submitId' => $id]);
            case 'team':
                return $this->generateUrl('jury_team', ['teamId' => $id]);
            case 'team_affiliation':
                return $this->generateUrl('jury_team_affiliation', ['affilId' => $id]);
            case 'team_category':
                return $this->generateUrl('jury_team_category', ['categoryId' => $id]);
            case 'user':
                // Pre 6.1, usernames were stored instead of numeric ids
                if (!is_numeric($id)) {
                    $user = $this->entityManager->getRepository(User::class)->findOneBy(['username'=>$id]);
                    $id = $user->getUserId();
                }
                return $this->generateUrl('jury_user', ['userId' => $id]);
            case 'testcase':
                $testcase = $this->entityManager->getRepository(Testcase::class)->find($id);
                if ($testcase) {
                    return $this->generateUrl('jury_problem_testcases', ['probId' => $testcase->getProbid()]);
                }
                break;
        }
        return null;
    }
}
