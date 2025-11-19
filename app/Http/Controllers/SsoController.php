<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole; 
use Illuminate\Support\Facades\URL; 
use Illuminate\Support\Facades\Session;

class SsoController extends Controller
{
    /**
     * Avvia il flusso SSO: genera lo stato CSRF e reindirizza a Passport.
     */
    public function redirect()
    {
        // === LOG PER CONFERMARE L'ESECUZIONE DEL METODO ===
        Log::info("SSO Redirect: Esecuzione del metodo 'redirect' iniziata.");
        // =======================================================
        
        // 1. Recupera la configurazione SSO dall'array config/services.php
        $config = config('services.sso');

        // Verifica configurazione essenziale
        if (empty($config['auth_url']) || empty($config['client_id'])) {
             Log::error("SSO Configuration Error: Missing AUTH_URL or CLIENT_ID in config/services.php");
             // DEBUG ESTREMO: Se la configurazione non c'è, restituisci un errore visibile.
             return response("ERRORE FATALE DI CONFIGURAZIONE SSO. Controllare 'config/services.php' e '.env'.", 500);
        }
        
        $state = Str::random(40);
        
        // Usa l'helper session() per la massima affidabilità
        session()->put('state', $state); 
        
        // Forza il salvataggio della sessione prima del reindirizzamento (FIX CHIAVE)
        Session::save();
        
        Log::debug("SSO Redirect: State saved in session. Saved State: " . session('state'));

        // Usa l'URI di reindirizzamento configurato nell'App Cliente (usa fallback locale se non definito)
        $redirectUri = $config['redirect_uri'] ?? URL::to('/auth/callback'); 

        $query = http_build_query([
            'client_id'     => $config['client_id'],
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $config['scope'],
            'state'         => $state, 
        ]);

        $finalUrl = $config['auth_url'] . '?' . $query;
        Log::info("SSO Redirect: Tentativo di reindirizzamento a: " . $finalUrl);
        
        // QUESTA RIGA ESEGUE IL REINDIRIZZAMENTO EFFETTIVO.
        return redirect($finalUrl);
    }
    
    /**
     * Gestisce il callback e lo scambio di token dopo l'autenticazione.
     */
    public function callback(Request $request)
    {
        // 1. Recupera la configurazione SSO dall'array config/services.php
        $config = config('services.sso');

        // 2. Verifica Stato di Sicurezza (CSRF Protection) e Errori
        $sessionState = session()->pull('state');
        $isError = $request->has('error');

        // Logging dello stato per debug
        Log::error("SSO Callback State Check:", [
            'session_state_retrieved' => $sessionState, 
            'request_state_received' => $request->state,
            'error_from_sso_server' => $request->error, 
            'line' => __LINE__,
        ]);


        if (!$sessionState || $sessionState !== $request->state || $isError) {
            $errorMessage = $request->error ?? 'Stato Sessione mancante'; 
            
            if ($request->error) {
                 Log::error("SSO Security/Error Failure: SSO Server Error Received: " . $request->error);
            }
            
            // Reindirizza al login di Filament
            return redirect('/admin/login')->withErrors(['sso' => 'Accesso SSO fallito o negato. Errore: ' . $errorMessage]);
        }
        
        // --- SE LA VERIFICA PASSA, PROSEGUIAMO CON LO SCAMBIO TOKEN ---

        // 3. Scambio Codice per Token
        $redirectUri = $config['redirect_uri'] ?? URL::to('/auth/callback');

        $response = Http::asForm()
                ->withOptions(['verify' => false]) 
                ->post($config['token_url'], [
                    'grant_type' => 'authorization_code',
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret'],
                    'redirect_uri' => $redirectUri,
                    'code' => $request->code,
                ]);

        $data = $response->json();
 
        if ($response->failed() || !isset($data['access_token'])) {
             Log::error('SSO Token Exchange Failed: Status: ' . $response->status());
             Log::error('SSO Token Exchange Failed: Body: ' . $response->body()); 

             return redirect('/admin/login')->withErrors(['sso' => 'Impossibile ottenere il token di accesso. Controlla il log di App Cliente per i dettagli dell\'errore Passport.']);
        }
        $accessToken = $data['access_token'];

        // 4. Recupera i Dati Utente dal Server IdP
        $userResponse = Http::withToken($accessToken)
                ->withOptions(['verify' => false]) 
                ->get($config['userinfo_url']);
        
        $ssoUserData = $userResponse->json();

        if ($userResponse->failed() || !isset($ssoUserData['email'])) {
             Log::error('SSO User Info Failed: ' . $userResponse->body());
             return redirect('/admin/login')->withErrors(['sso' => 'Impossibile recuperare i dati utente validi.']);
        }

        // 5. Provisioning/Shadow User Locale
        $user = User::firstOrCreate(
            ['email' => $ssoUserData['email']],
            [
                'name'     => $ssoUserData['name'] ?? $ssoUserData['email'],
                'password' => Hash::make(Str::random(40)), 
            ]
        );

        // 6. Acquisizione e Sincronizzazione del Ruolo tramite Spatie
        $ssoScope = $config['scope'];
        $ssoRole = $ssoUserData['application_roles'][$ssoScope] ?? null;

        // Verifica l'esistenza della classe SpatieRole prima di tentare l'assegnazione
        if ($ssoRole['name'] == "super_admin" && class_exists('Spatie\Permission\Models\Role')) {
            $user->syncRoles([]); 
            
            // Crea il ruolo se non esiste prima di assegnarlo
            $role = SpatieRole::firstOrCreate(
                ['name' => $ssoRole['name'], 'guard_name' => 'web']
            );
            

            $user->assignRole($role);
            $user->is_admin = true;
            $user->save();

            Log::info("SSO Login: User {$user->email} assigned role: {$ssoRole['name']} (Created if non-existent).");
        }

        // 7. Logga e Reindirizza a Filament
        Auth::login($user, true);
        return $user->loginRedirect();
    }

    public function handleSloCallback(Request $request)
    {
        // 1. Configurazione e Recupero dei Dati
        $sloKey = config('services.sso.slo_key');
        $incomingKey = $request->header('X-SLO-AUTH-KEY');
        // Si usa filter_var per garantire che l'input sia un intero valido, prevenendo iniezioni SQL implicite
        $userId = filter_var($request->input('user_id'), FILTER_VALIDATE_INT);

        // 2. AUTENTICAZIONE DELLA RICHIESTA (Chiave Segreta)
        if (empty($incomingKey) || $incomingKey !== $sloKey) {
            Log::warning('SLO Callback: Tentativo di accesso non autorizzato o chiave segreta mancante.', [
                'remote_ip' => $request->ip()
            ]);
            return response()->json(['message' => 'Unauthorized SLO request or invalid key.'], 401);
        }

        // 3. VERIFICA DEI DATI (ID Utente)
        if (!$userId) {
            return response()->json(['message' => 'Missing or invalid User ID.'], 400);
        }

        // 4. TERMINAZIONE DELLE SESSIONI LOCALI
        try {
            $updated = DB::table((new User)->getTable())
                     ->where('id', $userId)
                     ->update(['remember_token' => null]);
        
            Log::info("SLO: Remember token revocato per l'utente {$userId} (risultato: {$updated}).");

            // 2. TERMINAZIONE FORZATA DELLE SESSIONI WEB (RISOLVE FILAMENT)
            $deletedCount = DB::table('sessions')
                                ->where('user_id', $userId)
                                ->delete();
            
            Log::info("SLO: Terminate {$deletedCount} session(s) per l'utente {$userId}.");

            // 3. ELIMINAZIONE DEI COOKIE LOCALI
            $sessionCookieName = config('session.cookie'); 
            
            // Costruzione manuale del nome del cookie 'remember'
            // Rimuove spazi, rende minuscolo e prefissa. (Es: 'My App' -> 'remember_web_myapp')
            $appName = strtolower(str_replace(' ', '', config('app.name')));
            $rememberCookieName = 'remember_web_' . $appName; 
            
            return response()->json(['message' => "User ID {$userId} logged out completely."], 200)
                ->withCookie(Cookie::forget($sessionCookieName))
                ->withCookie(Cookie::forget($rememberCookieName));


        } catch (\Exception $e) {
            // Cattura qualsiasi errore che impedisca l'operazione di logout (es. problemi di connessione DB)
            Log::error("SLO Callback Fatal Error: " . $e->getMessage(), ['user_id' => $userId, 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Internal server error during session termination.'], 500);
        }
    }
}