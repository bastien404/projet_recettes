<?php

namespace App\Controller;

use App\Form\ExternalPostType; // Import the form type
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request; // Import Request
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/external/posts')] // Base route for all actions in this controller
class ExternalPostController extends AbstractController
{
    private HttpClientInterface $client;
    private string $apiUrlBase = 'https://jsonplaceholder.typicode.com/posts'; // Base API URL

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    // --- READ (List) ---
    #[Route('/', name: 'app_external_posts_index', methods: ['GET'])]
    public function index(CacheInterface $cache): Response
    {
        // --- 3. Mise en cache du traitement long ---
        $texteLong = $cache->get('mon_texte_long', function (ItemInterface $item) {
            // --- 7. Expiration du cache pour le traitement long ---
            $item->expiresAfter(20); // Expiration après 20 secondes
            $this->addFlash('info', 'Traitement long exécuté (non trouvé dans le cache ou expiré).');
            return $this->simuler_traitement_long();
        });

        // --- 5. Mise en cache de l'appel API ---
        $posts = $cache->get('external_api_posts', function (ItemInterface $item) {
            // --- 6. Expiration du cache pour l'API ---
            $item->expiresAfter(20); // Expiration après 20 secondes
            $this->addFlash('info', 'Appel API exécuté (non trouvé dans le cache ou expiré).');

            $fetchedPosts = []; // Initialiser au cas où l'appel échoue
            try {
                $response = $this->client->request('GET', $this->apiUrlBase);
                if (200 <= $response->getStatusCode() && $response->getStatusCode() < 300) {
                    $fetchedPosts = $response->toArray();
                } else {
                    $this->addFlash('error', '[CACHE] API a retourné un statut inattendu: ' . $response->getStatusCode());
                }
            } catch (ExceptionInterface $e) {
                $this->addFlash('error', '[CACHE] Impossible de contacter l\'API : ' . $e->getMessage());
                // Retourner un tableau vide ou null pour éviter de cacher une erreur ?
                // Ici on retourne vide pour ne pas planter le template Twig.
            }
            return $fetchedPosts; // La valeur retournée est mise en cache
        });

        // Affichage du texte pour vérifier (optionnel)
        $this->addFlash('warning', 'Résultat du traitement long : ' . $texteLong);

        return $this->render('external_post/index.html.twig', [
            'posts' => $posts,
        ]);
    }

    // --- READ (Show) ---
    #[Route('/{id}', name: 'app_external_posts_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $post = null;
        try {
            $response = $this->client->request('GET', $this->apiUrlBase . '/' . $id);

            if ($response->getStatusCode() === 200) {
                $post = $response->toArray();
            } elseif ($response->getStatusCode() === 404) {
                $this->addFlash('error', 'Post non trouvé (ID: ' . $id . ').');
                return $this->redirectToRoute('app_external_posts_index');
            } else {
                $this->addFlash('error', 'API a retourné un statut inattendu: ' . $response->getStatusCode());
            }
        } catch (ExceptionInterface $e) {
            $this->addFlash('error', 'Impossible de récupérer le post depuis l\'API : ' . $e->getMessage());
        }

        if (!$post) {
            $this->addFlash('error', 'Le post avec l\'ID ' . $id . ' n\'a pas pu être chargé.');
            return $this->redirectToRoute('app_external_posts_index');
        }

        return $this->render('external_post/show.html.twig', [
            'post' => $post,
        ]);
    }

    // --- CREATE (Display Form) ---
    #[Route('/new', name: 'app_external_posts_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(ExternalPostType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $postData = $form->getData();
            // JSONPlaceholder might auto-assign userId, typically based on auth if implemented
            // For now, let's hardcode one or omit it if the API allows
            $postData['userId'] = 1;

            try {
                $response = $this->client->request('POST', $this->apiUrlBase, [
                    'json' => $postData
                ]);

                if ($response->getStatusCode() === 201) { // 201 Created
                    $newPost = $response->toArray();
                    $this->addFlash('success', 'Post créé avec succès (ID: ' . $newPost['id'] . ').');
                    return $this->redirectToRoute('app_external_posts_show', ['id' => $newPost['id']]);
                } else {
                    $this->addFlash('error', 'Erreur lors de la création via API: Statut ' . $response->getStatusCode());
                }
            } catch (ExceptionInterface $e) {
                $this->addFlash('error', 'Impossible de créer le post via l\'API : ' . $e->getMessage());
            }
        }

        return $this->render('external_post/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // --- UPDATE (Display Form) ---
    #[Route('/{id}/edit', name: 'app_external_posts_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $post = null;
        // 1. Fetch existing data to pre-fill the form
        try {
            $response = $this->client->request('GET', $this->apiUrlBase . '/' . $id);
            if ($response->getStatusCode() === 200) {
                $post = $response->toArray();
            } else {
                $this->addFlash('error', 'Impossible de charger le post (ID: ' . $id . ') pour modification.');
                return $this->redirectToRoute('app_external_posts_index');
            }
        } catch (ExceptionInterface $e) {
            $this->addFlash('error', 'Erreur API lors du chargement du post: ' . $e->getMessage());
            return $this->redirectToRoute('app_external_posts_index');
        }

        if (!$post) { // Double check if post was loaded
            return $this->redirectToRoute('app_external_posts_index');
        }

        // 2. Create and handle the form
        $form = $this->createForm(ExternalPostType::class, $post); // Pre-fill with fetched data
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updatedData = $form->getData();
            // Important: Ensure userId is included if required by API, use original post's userId
            $updatedData['userId'] = $post['userId'] ?? 1; // Use original or default

            try {
                // Using PUT requires sending the *entire* resource representation
                $response = $this->client->request('PUT', $this->apiUrlBase . '/' . $id, [
                    'json' => $updatedData
                ]);

                if ($response->getStatusCode() === 200) {
                    $this->addFlash('success', 'Post mis à jour avec succès.');
                    return $this->redirectToRoute('app_external_posts_show', ['id' => $id]);
                } else {
                    $this->addFlash('error', 'Erreur lors de la mise à jour via API: Statut ' . $response->getStatusCode());
                }
            } catch (ExceptionInterface $e) {
                $this->addFlash('error', 'Impossible de mettre à jour le post via l\'API : ' . $e->getMessage());
            }
        }

        return $this->render('external_post/edit.html.twig', [
            'post' => $post, // Pass original post data for context if needed
            'form' => $form->createView(),
            'post_id' => $id // Pass ID separately for form action/delete button
        ]);
    }

    // --- DELETE ---
    #[Route('/{id}', name: 'app_external_posts_delete', methods: ['POST'])] // Use POST for deletion via browser form
    public function delete(Request $request, int $id): Response
    {
        // Simple CSRF check (use Symfony form's built-in CSRF for more robustness if needed)
        if ($this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            try {
                $response = $this->client->request('DELETE', $this->apiUrlBase . '/' . $id);

                // JSONPlaceholder returns 200 on successful DELETE
                if ($response->getStatusCode() === 200) {
                    $this->addFlash('success', 'Post supprimé avec succès.');
                } else {
                    // It might return 404 if already deleted, which is arguably a success too
                    $this->addFlash('error', 'Erreur lors de la suppression via API: Statut ' . $response->getStatusCode());
                }
            } catch (ExceptionInterface $e) {
                $this->addFlash('error', 'Impossible de supprimer le post via l\'API : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_external_posts_index');
    }

    private function simuler_traitement_long(): string
    {
        sleep(8); // Pause de 4 secondes
        return "c'était long !!";
    }
}
