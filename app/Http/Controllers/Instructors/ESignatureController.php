<?php

namespace App\Http\Controllers\Instructors;

use App\Http\Controllers\Controller;
use App\Models\Esignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ESignatureController extends Controller
{
    /**
     * Identify whichever guard is currently logged in (instructor / admin / registrar).
     * Mirrors the original save_esignature.php which checked three session keys in order.
     */
    private function signer(): array
    {
        foreach (['instructor', 'admin', 'registrar'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                return [
                    'id' => $user->getAuthIdentifier(),
                    'role' => $guard === 'instructor' ? 'instructor' : ($user->role ?? $guard),
                    'name' => $user->full_name ?? $user->getAuthIdentifier(),
                ];
            }
        }
        abort(401, 'Unauthorized');
    }

    // GET /esignature  (action=get)
    public function get()
    {
        [$id, $role] = array_values($this->signer());
        $sig = Esignature::where('signer_id', $id)->where('signer_role', $role)->first();

        if (! $sig) {
            return response()->json(['success' => true, 'has_signature' => false]);
        }

        return response()->json([
            'success' => true, 'has_signature' => true,
            'signature_data' => $sig->signature_data,
            'signer_name' => $sig->signer_name,
            'updated_at' => $sig->updated_at->format('F d, Y g:i A'),
        ]);
    }

    // POST /esignature  (action=save)
    public function save(Request $request)
    {
        $signer = $this->signer();

        $data = $request->validate([
            'signature_data' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:300000'],
        ]);

        $prefix = 'data:image/png;base64,';
        $binary = base64_decode(substr($data['signature_data'], strlen($prefix)), true);
        $image = $binary === false ? false : @getimagesizefromstring($binary);

        if ($binary === false
            || strlen($binary) > 200 * 1024
            || $image === false
            || ($image[2] ?? null) !== IMAGETYPE_PNG
            || ($image[0] ?? 0) < 1
            || ($image[1] ?? 0) < 1
            || ($image[0] ?? 0) > 2000
            || ($image[1] ?? 0) > 1000) {
            throw ValidationException::withMessages([
                'signature_data' => 'The signature must be a valid PNG image no larger than 200 KB or 2000 by 1000 pixels.',
            ]);
        }

        $signatureData = $prefix.base64_encode($binary);

        Esignature::updateOrCreate(
            ['signer_id' => $signer['id'], 'signer_role' => $signer['role']],
            ['signer_name' => $signer['name'], 'signature_data' => $signatureData]
        );

        return response()->json(['success' => true, 'message' => 'E-signature saved successfully.']);
    }

    // DELETE /esignature  (action=delete)
    public function delete()
    {
        $signer = $this->signer();

        Esignature::where('signer_id', $signer['id'])->where('signer_role', $signer['role'])->delete();

        return response()->json(['success' => true, 'message' => 'E-signature deleted.']);
    }
}
