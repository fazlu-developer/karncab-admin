<?php

namespace App\Services;

use App\Support\SimpleDocx;
use App\Support\SimplePdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class JoiningLetterService
{
    public function ensure(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('staff_designations')) {
            $schema->create('staff_designations', function ($table) {
                $table->id();
                $table->string('name', 120);
                $table->unsignedSmallInteger('sort_order')->default(10);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
        if (! $schema->hasTable('staff_letterhead')) {
            $schema->create('staff_letterhead', function ($table) {
                $table->id();
                $table->string('legal_name', 180);
                $table->string('brand_name', 80)->default('KarnaRide');
                $table->string('cin', 32)->nullable();
                $table->string('gstin', 20)->nullable();
                $table->string('office_address', 400);
                $table->string('signatory_name', 120);
                $table->string('signatory_title', 80)->default('Director');
                $table->string('powered_by', 80)->default('Joddo Corp');
                $table->string('header_image_path', 180)->nullable();
                $table->string('signature_path', 180)->nullable();
                $table->string('coi_path', 180)->nullable();
                $table->string('gst_path', 180)->nullable();
                $table->timestamps();
            });
        }
        if ($schema->hasTable('staff_letterhead') && ! $schema->hasColumn('staff_letterhead', 'header_image_path')) {
            $schema->table('staff_letterhead', function ($table) {
                $table->string('header_image_path', 180)->nullable()->after('powered_by');
            });
        }
        if (! $schema->hasTable('staff_joinings')) {
            $schema->create('staff_joinings', function ($table) {
                $table->id();
                $table->string('public_ref', 24)->nullable()->unique();
                $table->string('full_name', 180);
                $table->string('phone', 20);
                $table->string('email', 180)->nullable();
                $table->unsignedBigInteger('designation_id');
                $table->string('posting', 160);
                $table->string('address', 300)->nullable();
                $table->date('joining_on');
                $table->string('letter_type', 16);
                $table->unsignedInteger('offered_rupees')->nullable();
                $table->string('reporting_to', 160)->nullable();
                $table->string('document_hash', 64)->nullable();
                $table->timestamps();
            });
        }
        if (DB::connection('platform')->table('staff_designations')->count() === 0) {
            $now = now();
            $names = ['Director', 'State Head', 'District Head', 'Fleet Manager', 'Operations Executive', 'Accounts Executive', 'Customer Support'];
            foreach ($names as $i => $name) {
                DB::connection('platform')->table('staff_designations')->insert([
                    'name' => $name,
                    'sort_order' => ($i + 1) * 10,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        DB::connection('platform')->table('staff_letterhead')
            ->where('legal_name', 'like', '%karnacab%')
            ->update(['legal_name' => 'KarnaRide', 'updated_at' => now()]);
        if (DB::connection('platform')->table('staff_letterhead')->count() === 0) {
            $now = now();
            DB::connection('platform')->table('staff_letterhead')->insert([
                'legal_name' => 'KarnaRide',
                'brand_name' => 'KarnaRide',
                'cin' => 'U52291BR2024PTC072278',
                'gstin' => null,
                'office_address' => 'Ward no. 6/41, C/O Baleshwar Prasad, Refugee Colony, Kahara, Saharsa, Bihar 852202',
                'signatory_name' => 'Director',
                'signatory_title' => 'Director',
                'powered_by' => 'Joddo Corp',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function letterhead(): object
    {
        $this->ensure();

        return DB::connection('platform')->table('staff_letterhead')->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveLetterhead(array $input, ?UploadedFile $header, ?UploadedFile $signature, ?UploadedFile $coi, ?UploadedFile $gst): void
    {
        $row = $this->letterhead();
        $brand = trim((string) $input['brand_name']);
        $legal = trim((string) ($input['legal_name'] ?? ''));
        if ($legal === '' || stripos($legal, 'karnacab') !== false) {
            $legal = $brand !== '' ? $brand : 'KarnaRide';
        }
        $data = [
            'legal_name' => $legal,
            'brand_name' => $brand !== '' ? $brand : 'KarnaRide',
            'cin' => trim((string) ($input['cin'] ?? '')) ?: null,
            'gstin' => strtoupper(trim((string) ($input['gstin'] ?? ''))) ?: null,
            'office_address' => trim((string) $input['office_address']),
            'signatory_name' => trim((string) $input['signatory_name']),
            'signatory_title' => trim((string) $input['signatory_title']),
            'powered_by' => trim((string) ($input['powered_by'] ?? '')) ?: 'Joddo Corp',
            'updated_at' => now(),
        ];
        if ($requestClears = ! empty($input['clear_header'])) {
            $data['header_image_path'] = null;
        }
        if ($header && ! $requestClears) {
            $data['header_image_path'] = $this->storeImage($header, 'joining/header.jpg', 'Upload the letterhead image as a JPG or PNG.');
        }
        if ($signature) {
            $data['signature_path'] = $this->storeImage($signature, 'joining/signature.jpg', 'Upload the signature as a JPG or PNG image.');
        }
        if ($coi) {
            $data['coi_path'] = $coi->store('joining/company', 'local');
        }
        if ($gst) {
            $data['gst_path'] = $gst->store('joining/company', 'local');
        }
        DB::connection('platform')->table('staff_letterhead')->where('id', $row->id)->update($data);
    }

    /**
     * @return list<object>
     */
    public function designations(bool $activeOnly = false): array
    {
        $this->ensure();
        $query = DB::connection('platform')->table('staff_designations')->orderBy('sort_order')->orderBy('name');
        if ($activeOnly) {
            $query->where('active', true);
        }

        return $query->get()->all();
    }

    public function storeDesignation(string $name): void
    {
        $this->ensure();
        $name = trim($name);
        abort_if($name === '', 422, 'Enter a designation.');
        $exists = DB::connection('platform')->table('staff_designations')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();
        abort_if($exists, 422, 'That designation is already in the list.');
        $sort = (int) DB::connection('platform')->table('staff_designations')->max('sort_order') + 10;
        $now = now();
        DB::connection('platform')->table('staff_designations')->insert([
            'name' => $name,
            'sort_order' => $sort,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateDesignation(int $id, string $name, bool $active): void
    {
        $this->ensure();
        DB::connection('platform')->table('staff_designations')->where('id', $id)->update([
            'name' => trim($name),
            'active' => $active,
            'updated_at' => now(),
        ]);
    }

    public function destroyDesignation(int $id): void
    {
        $this->ensure();
        $used = DB::connection('platform')->table('staff_joinings')->where('designation_id', $id)->exists();
        abort_if($used, 422, 'This designation is used on a letter. Turn it off instead of deleting it.');
        DB::connection('platform')->table('staff_designations')->where('id', $id)->delete();
    }

    /**
     * @param  array{q?: string, letter_type?: string, designation_id?: int, per_page?: int}  $filters
     */
    public function joinings(array $filters = []): LengthAwarePaginator
    {
        $this->ensure();
        $query = DB::connection('platform')->table('staff_joinings as j')
            ->leftJoin('staff_designations as d', 'd.id', '=', 'j.designation_id');
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('j.full_name', 'like', $like)
                    ->orWhere('j.phone', 'like', $like)
                    ->orWhere('j.email', 'like', $like)
                    ->orWhere('j.public_ref', 'like', $like)
                    ->orWhere('j.posting', 'like', $like)
                    ->orWhere('d.name', 'like', $like);
            });
        }
        $type = (string) ($filters['letter_type'] ?? '');
        if (in_array($type, ['offer', 'appointment'], true)) {
            $query->where('j.letter_type', $type);
        }
        $designationId = (int) ($filters['designation_id'] ?? 0);
        if ($designationId > 0) {
            $query->where('j.designation_id', $designationId);
        }
        $perPage = (int) ($filters['per_page'] ?? 10);
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        return $query->orderByDesc('j.id')->paginate($perPage, [
            'j.*',
            'd.name as designation_name',
        ])->withQueryString();
    }

    public function find(int $id): object
    {
        $this->ensure();
        $row = DB::connection('platform')->table('staff_joinings')->where('id', $id)->first();
        abort_unless($row, 404);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): int
    {
        $this->ensure();
        $now = now();
        $id = DB::connection('platform')->table('staff_joinings')->insertGetId($this->payload($input) + [
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $type = $input['letter_type'] === 'appointment' ? 'KR-APT-' : 'KR-OFR-';
        DB::connection('platform')->table('staff_joinings')->where('id', $id)->update([
            'public_ref' => $type.str_pad((string) $id, 5, '0', STR_PAD_LEFT),
        ]);

        return (int) $id;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(int $id, array $input): void
    {
        $this->find($id);
        DB::connection('platform')->table('staff_joinings')->where('id', $id)->update($this->payload($input) + [
            'document_hash' => null,
            'updated_at' => now(),
        ]);
    }

    public function destroy(int $id): void
    {
        $this->ensure();
        DB::connection('platform')->table('staff_joinings')->where('id', $id)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(int $id): array
    {
        $row = $this->find($id);
        $head = $this->letterhead();
        $designation = (string) (DB::connection('platform')->table('staff_designations')->where('id', $row->designation_id)->value('name') ?: 'Employee');
        $hash = $this->seal($head, $row, $designation);
        DB::connection('platform')->table('staff_joinings')->where('id', $id)->update([
            'document_hash' => $hash,
            'updated_at' => now(),
        ]);
        $row->document_hash = $hash;
        $copy = $this->letterCopy($head, $row, $designation);

        return [
            'row' => $row,
            'head' => $head,
            'designation' => $designation,
            'hash' => $hash,
            'title' => $copy['title'],
            'date' => $copy['date'],
            'salary' => $copy['salary'],
            'issued' => $copy['issued'],
            'paragraphs' => $copy['paragraphs'],
            'logoUrl' => $this->logoPreviewUrl($head),
            'showSignature' => $this->signatureJpeg($head->signature_path ?? null) !== null,
        ];
    }

    public function pdf(int $id): string
    {
        $data = $this->present($id);

        return $this->renderPdf($data['head'], $data['row'], $data['designation'], $data['hash']);
    }

    public function docx(int $id): string
    {
        $data = $this->present($id);

        return $this->renderDocx($data);
    }

    /**
     * @return array{bytes: string, mime: string, name: string}
     */
    public function letterheadFile(string $kind): array
    {
        $head = $this->letterhead();
        if ($kind === 'logo') {
            $jpeg = $this->logoJpeg($head);
            abort_unless(is_string($jpeg) && $jpeg !== '', 404);

            return ['bytes' => $jpeg, 'mime' => 'image/jpeg', 'name' => 'letterhead.jpg'];
        }
        $column = match ($kind) {
            'header' => 'header_image_path',
            'signature' => 'signature_path',
            'coi' => 'coi_path',
            'gst' => 'gst_path',
            default => null,
        };
        $path = $column ? ($head->{$column} ?? null) : null;
        abort_unless(is_string($path) && $path !== '' && Storage::disk('local')->exists($path), 404);
        $bytes = Storage::disk('local')->get($path);
        abort_unless(is_string($bytes) && $bytes !== '', 404);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        return ['bytes' => $bytes, 'mime' => $mime, 'name' => basename($path)];
    }

    public function logoPreviewUrl(object $head): ?string
    {
        return $this->logoJpeg($head) ? route('joining.letterhead.file', 'logo') : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function payload(array $input): array
    {
        $amount = $input['offered_rupees'] ?? null;

        return [
            'full_name' => trim((string) $input['full_name']),
            'phone' => trim((string) $input['phone']),
            'email' => trim((string) ($input['email'] ?? '')) ?: null,
            'designation_id' => (int) $input['designation_id'],
            'posting' => trim((string) $input['posting']),
            'address' => trim((string) ($input['address'] ?? '')) ?: null,
            'joining_on' => (string) $input['joining_on'],
            'letter_type' => $input['letter_type'] === 'appointment' ? 'appointment' : 'offer',
            'offered_rupees' => $amount === null || $amount === '' ? null : (int) round((float) $amount),
            'reporting_to' => trim((string) ($input['reporting_to'] ?? '')) ?: 'Director, KarnaRide',
        ];
    }

    private function seal(object $head, object $row, string $designation): string
    {
        return hash('sha256', implode('|', [
            (string) $head->legal_name,
            (string) $head->cin,
            (string) $head->gstin,
            (string) $row->public_ref,
            (string) $row->letter_type,
            (string) $row->full_name,
            $designation,
            (string) $row->posting,
            (string) $row->joining_on,
            (string) $row->offered_rupees,
            (string) $row->reporting_to,
        ]));
    }

    private function renderPdf(object $head, object $row, string $designation, string $hash): string
    {
        $pdf = new SimplePdf();
        $brand = 'KarnaRide';
        $copy = $this->letterCopy($head, $row, $designation);
        $title = $copy['title'];
        $issued = $copy['issued'];
        $paragraphs = $copy['paragraphs'];

        $pdf->fill(0, 0, 10, 842, '1B7A32');
        $pdf->fill(10, 0, 4, 842, 'F5C518');
        $logo = $this->logoJpeg($head);
        if ($logo !== null) {
            $index = $pdf->addJpeg($logo);
            $info = @getimagesizefromstring($logo);
            $lw = (int) ($info[0] ?? 3);
            $lh = (int) ($info[1] ?? 1);
            $drawW = 180.0;
            $drawH = min(52, $drawW * $lh / max(1, $lw));
            $pdf->drawImage($index, 32, 18, $drawW, $drawH);
        } else {
            $pdf->text(36, 28, 20, $brand, '1B7A32', true);
        }
        $pdf->text(390, 24, 13, $title, '1B7A32', true);
        $pdf->text(390, 42, 9, 'Ref  '.$row->public_ref, '555555');
        $pdf->text(390, 56, 9, 'Issued  '.$issued, '555555');
        $pdf->fill(36, 96, 523, 1.5, '1B7A32');

        $y = 114;
        $pdf->text(36, $y, 12, 'Dear '.$row->full_name.',', '1A1A1A', true);
        $y += 24;
        foreach ($paragraphs as $parts) {
            $y = $pdf->writeRich(36, $y, 11, 520, $parts, '333333', 5);
            $y += 8;
        }
        $pdf->text(36, $y, 11, 'With warm regards,', '333333');
        $y += 20;
        $pdf->text(36, $y, 11, 'For '.$brand, '1B7A32', true);
        $y += 8;
        $signature = $this->signatureJpeg($head->signature_path ?? null);
        if ($signature !== null) {
            $sign = $pdf->addJpeg($signature);
            $info = @getimagesizefromstring($signature);
            $sw = (int) ($info[0] ?? 1);
            $sh = (int) ($info[1] ?? 1);
            $drawW = 150.0;
            $drawH = max(28, min(58, $drawW * $sh / max(1, $sw)));
            $pdf->drawImage($sign, 36, $y, $drawW, $drawH);
            $y += $drawH + 6;
        } else {
            $y += 22;
        }
        $pdf->text(36, $y, 12, (string) $head->signatory_name, '111111', true);
        $y += 16;
        $pdf->text(36, $y, 10, (string) $head->signatory_title.', '.$brand, '555555');

        $pdf->fill(0, 792, 595, 50, 'F7F8F4');
        $pdf->fill(0, 792, 14, 50, '1B7A32');
        $office = trim((string) $head->office_address);
        $pdf->text(36, 802, 8, $brand.'  ·  Ride  ·  Travel  ·  Parcel', '1B7A32', true);
        $pdf->text(36, 814, 8, ($office !== '' ? $office.'  ·  ' : '').'Powered by '.$head->powered_by, '666666');
        $pdf->text(36, 826, 7, 'Digital seal '.$hash, '888888');

        return $pdf->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderDocx(array $data): string
    {
        $doc = new SimpleDocx();
        $head = $data['head'];
        $row = $data['row'];
        $logo = $this->logoJpeg($head);
        if ($logo !== null) {
            $info = @getimagesizefromstring($logo);
            $lw = (int) ($info[0] ?? 3);
            $lh = (int) ($info[1] ?? 1);
            $drawW = 220;
            $drawH = max(36, (int) round($drawW * $lh / max(1, $lw)));
            $doc->image($logo, $drawW, min(80, $drawH));
        }
        $doc->paragraph([[$data['title'], true]], 28, 40);
        $doc->paragraph([['Ref '.$row->public_ref.'    Issued '.$data['issued'], false]], 18, 200);
        $doc->paragraph([['Dear '.$row->full_name.',', true]], 24, 160);
        foreach ($data['paragraphs'] as $parts) {
            $doc->paragraph($this->spacedRuns($parts), 22, 140);
        }
        $doc->paragraph([['With warm regards,', false]], 22, 40);
        $doc->paragraph([['For KarnaRide', true]], 22, 80);
        $signature = $this->signatureJpeg($head->signature_path ?? null);
        if ($signature !== null) {
            $info = @getimagesizefromstring($signature);
            $sw = (int) ($info[0] ?? 1);
            $sh = (int) ($info[1] ?? 1);
            $drawW = 160;
            $drawH = max(28, min(70, (int) round($drawW * $sh / max(1, $sw))));
            $doc->image($signature, $drawW, $drawH);
        }
        $doc->paragraph([[(string) $head->signatory_name, true]], 24, 20);
        $doc->paragraph([[$head->signatory_title.', KarnaRide', false]], 20, 200);
        $office = trim((string) $head->office_address);
        $doc->paragraph([['KarnaRide  ·  Ride  ·  Travel  ·  Parcel', true]], 18, 40);
        $doc->paragraph([[($office !== '' ? $office.'  ·  ' : '').'Powered by '.$head->powered_by, false]], 16, 40);
        $doc->paragraph([['Digital seal '.$data['hash'], false]], 14, 0);

        return $doc->render();
    }

    /**
     * @param  list<array{0: string, 1: bool}>  $parts
     * @return list<array{0: string, 1: bool}>
     */
    private function spacedRuns(array $parts): array
    {
        $out = [];
        $first = true;
        foreach ($parts as [$text, $bold]) {
            $text = (string) $text;
            if (! $first && ! preg_match('/^\s|^[.,;:]/', $text)) {
                $text = ' '.$text;
            }
            $first = false;
            $out[] = [$text, $bold];
        }

        return $out;
    }

    /**
     * @return array{title: string, date: string, salary: string, issued: string, paragraphs: list<list<array{0: string, 1: bool}>>}
     */
    private function letterCopy(object $head, object $row, string $designation): array
    {
        $date = date('d F Y', strtotime((string) $row->joining_on) ?: time());
        $salary = $row->offered_rupees ? 'Rs '.number_format((int) $row->offered_rupees) : 'As agreed';
        $title = $row->letter_type === 'appointment' ? 'Appointment letter' : 'Offer letter';
        $isAppointment = $row->letter_type === 'appointment';
        $paragraphs = $isAppointment
            ? [
                [['KarnaRide is pleased to confirm your appointment as', false], [$designation, true], ['. You will work with us at', false], [$row->posting, true], ['and you will report to', false], [(string) $row->reporting_to, true], ['.', false]],
                [['Your joining date is', false], [$date, true], ['. From that day you are a part of the KarnaRide team for ride, travel and parcel services.', false]],
                [['Your monthly salary is', false], [$salary, true], [', payable as per the company salary cycle, before statutory deductions.', false]],
                [['Your duties are those of a', false], [$designation, true], ['. KarnaRide may guide the day-to-day work. You will keep the company rules, serve customers with care, and protect the trust placed in this role.', false]],
                [['Please join on', false], [$date, true], ['and bring this letter on your first day. We are glad to welcome you and we look forward to a long association.', false]],
            ]
            : [
                [['KarnaRide is pleased to offer you the position of', false], [$designation, true], ['. This offer is made with confidence in the work you will do for our ride, travel and parcel services.', false]],
                [['You will be posted at', false], [$row->posting, true], ['and you will report to', false], [(string) $row->reporting_to, true], ['. Your place of work may be the office or the field, as the role requires.', false]],
                [['Your joining date is', false], [$date, true], ['. Kindly be present on this date so that your introduction, records and first day of work can be completed.', false]],
                [['Your monthly salary is', false], [$salary, true], ['. This amount is offered per month, before statutory deductions, and will be paid as per the company salary cycle.', false]],
                [['The duties of this role are the duties of a', false], [$designation, true], ['. You will follow the directions of KarnaRide, deal fairly with customers and partners, and keep confidential any information that belongs to the company.', false]],
                [['This offer is issued under the authority of the', false], [(string) $head->signatory_title, true], ['of KarnaRide. Please confirm your acceptance by joining on', false], [$date, true], ['. We look forward to starting this journey with you.', false]],
            ];
        if ($row->phone || $row->email) {
            $paragraphs[] = [['This letter is addressed to you at', false], [trim($row->phone.'  '.$row->email), true], ['. Please tell us if any of these details should be corrected.', false]];
        }

        return [
            'title' => $title,
            'date' => $date,
            'salary' => $salary,
            'issued' => now()->format('d F Y'),
            'paragraphs' => $paragraphs,
        ];
    }

    private function logoJpeg(object $head): ?string
    {
        if (! empty($head->header_image_path) && Storage::disk('local')->exists($head->header_image_path)) {
            $bytes = Storage::disk('local')->get($head->header_image_path);
            $jpeg = is_string($bytes) ? $this->asJpeg($bytes) : null;
            if ($jpeg !== null) {
                return $jpeg;
            }
        }
        $file = $this->brandingLogoFile();
        if ($file === null) {
            return null;
        }
        $bytes = file_get_contents($file);

        return is_string($bytes) ? $this->asJpeg($bytes) : null;
    }

    private function brandingLogoFile(): ?string
    {
        $dir = public_path('uploads/branding');
        $files = is_dir($dir) ? glob($dir.DIRECTORY_SEPARATOR.'logo-*.jpeg') : [];
        if (! is_array($files) || $files === []) {
            $files = is_dir($dir) ? glob($dir.DIRECTORY_SEPARATOR.'logo-*.jpg') : [];
        }
        if (! is_array($files) || $files === []) {
            return null;
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function storeImage(UploadedFile $file, string $path, string $error): string
    {
        $bytes = file_get_contents($file->getRealPath());
        abort_unless(is_string($bytes) && $bytes !== '', 422, $error);
        $jpeg = $this->asJpeg($bytes);
        abort_unless($jpeg !== null, 422, $error);
        Storage::disk('local')->put($path, $jpeg);

        return $path;
    }

    private function signatureJpeg(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($path);

        return $this->asJpeg($bytes);
    }

    private function asJpeg(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return (getimagesizefromstring($bytes)[2] ?? 0) === IMAGETYPE_JPEG ? $bytes : null;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        $flat = $this->onWhite($image);
        imagedestroy($image);
        if ($this->isSolidBlack($flat)) {
            imagedestroy($flat);

            return null;
        }
        $trimmed = $this->trimMargins($flat);
        imagedestroy($flat);
        ob_start();
        imagejpeg($trimmed, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($trimmed);

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }

    private function onWhite(\GdImage $image): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $canvas = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
        imagealphablending($canvas, true);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 127;
                if ($alpha > 120) {
                    continue;
                }
                $cover = 1 - ($alpha / 127);
                $r = ($rgba >> 16) & 255;
                $g = ($rgba >> 8) & 255;
                $b = $rgba & 255;
                $nr = (int) round($r * $cover + 255 * (1 - $cover));
                $ng = (int) round($g * $cover + 255 * (1 - $cover));
                $nb = (int) round($b * $cover + 255 * (1 - $cover));
                imagesetpixel($canvas, $x, $y, imagecolorallocate($canvas, $nr, $ng, $nb));
            }
        }

        return $canvas;
    }

    private function isSolidBlack(\GdImage $image): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $dark = 0;
        $samples = 0;
        $step = max(1, (int) floor(max($w, $h) / 40));
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $samples++;
                $light = ((($rgb >> 16) & 255) + (($rgb >> 8) & 255) + ($rgb & 255)) / 3;
                if ($light < 30) {
                    $dark++;
                }
            }
        }

        return $samples > 0 && ($dark / $samples) > 0.92;
    }

    private function trimMargins(\GdImage $image): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $minX = $w;
        $minY = $h;
        $maxX = 0;
        $maxY = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $light = ((($rgb >> 16) & 255) + (($rgb >> 8) & 255) + ($rgb & 255)) / 3;
                if ($light < 245) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }
        if ($maxX <= $minX || $maxY <= $minY) {
            $copy = imagecreatetruecolor($w, $h);
            imagecopy($copy, $image, 0, 0, 0, 0, $w, $h);

            return $copy;
        }
        $pad = 8;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($w - 1, $maxX + $pad);
        $maxY = min($h - 1, $maxY + $pad);
        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;
        $copy = imagecreatetruecolor($cw, $ch);
        $white = imagecolorallocate($copy, 255, 255, 255);
        imagefilledrectangle($copy, 0, 0, $cw, $ch, $white);
        imagecopy($copy, $image, 0, 0, $minX, $minY, $cw, $ch);

        return $copy;
    }
}
