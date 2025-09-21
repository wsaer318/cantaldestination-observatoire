<?php

declare(strict_types=1);

namespace App\Modules\SharedSpaces;


use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

class SharedSpacesController extends Controller
{
    private \SharedSpaceManager $spaces;

    public function __construct()
    {
        $this->spaces = new \SharedSpaceManager();
    }

    public function index(Request $request): Response
    {
        $user = $this->currentAdmin();
        $includeInactive = filter_var($request->getQuery('include_inactive', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $spaces = $this->spaces->getUserSpaces($user['id'], $includeInactive);
            $spaces = $this->attachStats($spaces);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'data' => $spaces,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $this->currentAdmin();
        $this->validateCsrf($this->extractCsrfToken($request));

        $name = trim((string) $request->json('name'));
        $description = trim((string) ($request->json('description') ?? ''));
        $initialMembers = $request->json('members', []);

        if ($name === '') {
            throw HttpException::badRequest("Le nom de l'espace est requis");
        }

        if (!is_array($initialMembers)) {
            throw HttpException::badRequest('Le champ members doit Ãªtre un tableau');
        }

        try {
            $spaceId = (int) $this->spaces->createSpace($name, $description, (int) $user['id'], $initialMembers);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'data' => ['id' => $spaceId],
            'message' => 'Espace crÃ©Ã© avec succÃ¨s',
        ], 201);
    }

    public function show(Request $request, int $spaceId): Response
    {
        $user = $this->currentAdmin();
        $space = $this->ensureSpace($spaceId, $user);

        try {
            $members = $this->spaces->getSpaceMembers($spaceId);
            $stats = $this->spaces->getSpaceStats($spaceId);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'data' => [
                'space' => $space,
                'members' => $members,
                'stats' => $stats,
            ],
        ]);
    }

    public function update(Request $request, int $spaceId): Response
    {
        $user = $this->currentAdmin();
        $this->validateCsrf($this->extractCsrfToken($request));
        $this->ensureSpace($spaceId, $user);

        $name = trim((string) $request->json('name'));
        $description = trim((string) ($request->json('description') ?? ''));

        if ($name === '') {
            throw HttpException::badRequest("Le nom de l'espace est requis");
        }

        try {
            $this->spaces->updateSpace($spaceId, $name, $description, (int) $user['id']);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'message' => 'Espace modifiÃ© avec succÃ¨s',
        ]);
    }

    public function destroy(Request $request, int $spaceId): Response
    {
        $user = $this->currentAdmin();
        $this->validateCsrf($this->extractCsrfToken($request));
        $this->ensureSpace($spaceId, $user);

        try {
            $this->spaces->deleteSpace($spaceId, (int) $user['id']);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'message' => 'Espace supprimÃ© avec succÃ¨s',
        ]);
    }

    public function members(Request $request, int $spaceId): Response
    {
        $user = $this->currentAdmin();
        $this->ensureSpace($spaceId, $user);

        try {
            $members = $this->spaces->getSpaceMembers($spaceId);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    public function addMember(Request $request, int $spaceId): Response
    {
        $user = $this->currentAdmin();
        $this->validateCsrf($this->extractCsrfToken($request));
        $this->ensureSpace($spaceId, $user);

        $memberId = (int) ($request->json('user_id') ?? 0);
        $role = trim((string) ($request->json('role') ?? 'reader'));

        if ($memberId <= 0) {
            throw HttpException::badRequest("L'identifiant utilisateur est requis");
        }

        if ($role === '') {
            throw HttpException::badRequest('Le rÃ´le est requis');
        }

        try {
            $this->spaces->addMember($spaceId, $memberId, $role);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'message' => 'Membre ajoutÃ© avec succÃ¨s',
        ], 201);
    }

    public function updateMember(Request $request, int $spaceId, int $memberId): Response
    {
        $user = $this->currentAdmin();
        $this->validateCsrf($this->extractCsrfToken($request));
        $this->ensureSpace($spaceId, $user);

        $newRole = trim((string) ($request->json('role') ?? ''));
        if ($newRole === '') {
            throw HttpException::badRequest('Le nouveau rÃ´le est requis');
        }

        try {
            $this->spaces->updateMemberRole($spaceId, $memberId, $newRole, (int) $user['id']);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'message' => 'RÃ´le modifiÃ© avec succÃ¨s',
        ]);
    }

    public function removeMember(Request $request, int $spaceId, int $memberId): Response
    {
        $user = $this->currentAdmin();
        $this->validateCsrf($this->extractCsrfToken($request));
        $this->ensureSpace($spaceId, $user);

        try {
            $this->spaces->removeMember($spaceId, $memberId, (int) $user['id']);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'message' => 'Membre retirÃ© avec succÃ¨s',
        ]);
    }

    public function listInfographics(Request $request, int $spaceId): Response
    {
        $user = $this->currentAdmin();
        $this->ensureSpace($spaceId, $user);

        $filters = [];
        $status = $request->getQuery('status');
        $search = $request->getQuery('search');
        if ($status !== null && $status !== '') {
            $filters['status'] = $status;
        }
        if ($search !== null && $search !== '') {
            $filters['search'] = $search;
        }

        try {
            $manager = new \InfographicManager();
            $infographics = $manager->getSpaceInfographics($spaceId, (int) $user['id'], $filters);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'data' => $infographics,
        ]);
    }

    public function shareInfographic(Request $request, int $spaceId): Response
    {
        $user = $this->currentAdmin();
        $this->validateCsrf($this->extractCsrfToken($request));
        $this->ensureSpace($spaceId, $user);

        $title = trim((string) ($request->json('title') ?? ''));
        $description = trim((string) ($request->json('description') ?? ''));
        $infographicData = $request->json('infographic_data');
        $tags = $request->json('tags', []);

        if ($title === '') {
            throw HttpException::badRequest("Le titre de l'infographie est requis");
        }

        if ($infographicData === null) {
            throw HttpException::badRequest("Les donnÃ©es de l'infographie sont requises");
        }

        if (!is_array($tags)) {
            throw HttpException::badRequest('Le champ tags doit Ãªtre un tableau');
        }

        try {
            $manager = new \InfographicManager();
            $infographicId = (int) $manager->createInfographic(
                $spaceId,
                $title,
                $description,
                (int) $user['id'],
                $infographicData,
                $tags
            );
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        return $this->json([
            'success' => true,
            'data' => ['id' => $infographicId],
            'message' => 'Infographie crÃ©Ã©e avec succÃ¨s',
        ], 201);
    }

    private function currentAdmin(): array
    {
        if (!\Auth::isAuthenticated()) {
            throw HttpException::unauthorized();
        }

        $user = \Auth::getUser();
        if (!$user || ($user['role'] ?? null) !== 'admin') {
            throw HttpException::forbidden('AccÃ¨s rÃ©servÃ© aux administrateurs');
        }

        return $user;
    }

    private function ensureSpace(int $spaceId, array $user): array
    {
        try {
            $space = $this->spaces->getSpace($spaceId, (int) $user['id']);
        } catch (\Throwable $exception) {
            throw HttpException::badRequest($exception->getMessage());
        }

        if (!$space) {
            throw HttpException::notFound('Espace non trouvÃ© ou accÃ¨s refusÃ©');
        }

        return $space;
    }

    private function attachStats(array $spaces): array
    {
        $result = [];
        foreach ($spaces as $space) {
            try {
                $stats = $this->spaces->getSpaceStats((int) $space['id']);
            } catch (\Throwable $exception) {
                $stats = [];
            }
            $result[] = array_merge($space, ['stats' => $stats]);
        }

        return $result;
    }

    private function validateCsrf(?string $token): void
    {
        if (!\Security::validateCSRFToken($token ?? '')) {
            throw HttpException::forbidden('Token CSRF invalide');
        }
    }
    public function listInfographicsLegacy(Request $request, int $spaceId): Response
    {
        return $this->listInfographics($request, $spaceId);
    }

    public function shareInfographicLegacy(Request $request, int $spaceId): Response
    {
        return $this->shareInfographic($request, $spaceId);
    }

    public function membersLegacy(Request $request, int $spaceId): Response
    {
        return $this->members($request, $spaceId);
    }

    public function addMemberLegacy(Request $request, int $spaceId): Response
    {
        return $this->addMember($request, $spaceId);
    }

    public function updateMemberLegacy(Request $request, int $spaceId, int $memberId): Response
    {
        return $this->updateMember($request, $spaceId, $memberId);
    }

    public function removeMemberLegacy(Request $request, int $spaceId, int $memberId): Response
    {
        return $this->removeMember($request, $spaceId, $memberId);
    }

    private function extractCsrfToken(Request $request): ?string
    {
        $headerToken = $request->getHeader('x-csrf-token');
        if ($headerToken !== null && $headerToken !== '') {
            return $headerToken;
        }

        $jsonToken = $request->json('csrf_token');
        if ($jsonToken !== null && $jsonToken !== '') {
            return (string) $jsonToken;
        }

        return $request->input('csrf_token');
    }
}



