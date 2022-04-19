<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateQrcodeRequest;
use App\Http\Requests\UpdateQrcodeRequest;
use App\Repositories\QrcodeRepository;
use App\Http\Controllers\AppBaseController;
use App\Models\Qrcode;
use Illuminate\Http\Request;
use Flash;
use Response;
use App\Models\Sample;
use Auth;
use App\Models\Field;

class QrcodeController extends AppBaseController
{
    /** @var  QrcodeRepository */
    private $qrcodeRepository;

    public function __construct(QrcodeRepository $qrcodeRepo)
    {
        $this->qrcodeRepository = $qrcodeRepo;
    }

    /**
     * Display a listing of the Qrcode.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $qrcodes = Qrcode::with(['point', 'point.polygon', 'point.polygon.field'])->get();

        return view('qrcodes.index')
            ->with('qrcodes', $qrcodes);
    }

    /**
     * Show the form for creating a new Qrcode.
     *
     * @return Response
     */
    public function create()
    {
        return view('qrcodes.create');
    }

    /**
     * Store a newly created Qrcode in storage.
     *
     * @param CreateQrcodeRequest $request
     *
     * @return Response
     */
    public function store(CreateQrcodeRequest $request)
    {
        $input = $request->all();

        $qrcode = $this->qrcodeRepository->create($input);

        Flash::success(__('messages.saved', ['model' => __('models/qrcodes.singular')]));

        return redirect(route('qrcodes.index'));
    }

    /**
     * Display the specified Qrcode.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {   
        $qrcode = $this->qrcodeRepository->find($id);

        if (empty($qrcode)) {
            Flash::error(__('messages.not_found', ['model' => __('models/qrcodes.singular')]));

            return redirect(route('qrcodes.index'));
        }

        $url = route('qrcodes.scan', ['id' => $qrcode->id]);
        $url = 'http://185.146.3.112/plesk-site-preview/cemextest.kz/https/185.146.3.112/qrcodes/' . $qrcode->id . '/scan';

        \QrCode::size(512)
            ->format('svg')
            ->generate($url, public_path('img/qrcodes/qrcode_' . $qrcode->id . '.svg'));

        return view('qrcodes.show')->with('qrcode', $qrcode);
    }

    /**
     * Download all QR codes for specific field
     * 
     * @param int $fieldId field id
     * 
     * @return Response
     */
    public function downloadAll(Request $request, $fieldId) {
        $field = Field::with(['polygon', 'polygon.points', 'polygon.points.qrcode'])->findOrFail($fieldId);
        // dd($field->toArray());
        
        // prepare zip
        $zipname = public_path('docs/qrcodes_' . $field->id . '.zip');

        $zip = new \ZipArchive();      
        $zip->open($zipname, \ZipArchive::CREATE);

        // add to zip
        foreach ($field->polygon->points as $point) {
            $url = 'http://185.146.3.112/plesk-site-preview/cemextest.kz/https/185.146.3.112/qrcodes/' . $point->qrcode->id . '/scan';

            $path = public_path('img/qrcodes/png_qrcode_' . $point->qrcode->id . '.png');

            \QrCode::size(512)
                ->format('png')
                ->generate($url, $path);
        
            $zip->addFile($path, 'QR метки №' . $point->num . '.png');
        }

        $zip->close();

        header('Content-Type: application/zip');
        header("Content-Transfer-Encoding: Binary");
        header("Content-Disposition: attachment; filename=QrCodes-" . $field->id . ".zip");
        header("Pragma: no-cache"); 
        header("Expires: 0");
        header('Cache-Control: must-revalidate');
        header('Content-Length: ' . filesize($zipname));

        // download
        readfile($zipname);

        return 'put inside zip & download it';
    }
    

    /**
     * Show the form for editing the specified Qrcode.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $qrcode = $this->qrcodeRepository->find($id);

        if (empty($qrcode)) {
            Flash::error(__('messages.not_found', ['model' => __('models/qrcodes.singular')]));

            return redirect(route('qrcodes.index'));
        }

        return view('qrcodes.edit')->with('qrcode', $qrcode);
    }

    /**
     * Update the specified Qrcode in storage.
     *
     * @param int $id
     * @param UpdateQrcodeRequest $request
     *
     * @return Response
     */
    public function update($id, UpdateQrcodeRequest $request)
    {
        $qrcode = $this->qrcodeRepository->find($id);

        if (empty($qrcode)) {
            Flash::error(__('messages.not_found', ['model' => __('models/qrcodes.singular')]));

            return redirect(route('qrcodes.index'));
        }

        $qrcode = $this->qrcodeRepository->update($request->all(), $id);

        Flash::success(__('messages.updated', ['model' => __('models/qrcodes.singular')]));

        return redirect(route('qrcodes.index'));
    }

    /**
     * Remove the specified Qrcode from storage.
     *
     * @param int $id
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        return;
        // dd($request);

        $qrcode = $this->qrcodeRepository->find($id);

        if (empty($qrcode)) {
            Flash::error(__('messages.not_found', ['model' => __('models/qrcodes.singular')]));

            return redirect(route('qrcodes.index'));
        }

        $this->qrcodeRepository->delete($id);

        Flash::success(__('messages.deleted', ['model' => __('models/qrcodes.singular')]));

        return redirect(route('qrcodes.index'));
    }


    public function scan(Request $request, $id) {
        $qrcode = Qrcode::with(['point'])->findOrFail($id);

        $sample = Sample::firstOrCreate([
            'point_id' => $qrcode->point->id,
        ]);

        $sample->date_selected = now();
        $sample->date_received = now();
        $sample->num = 0;
        $sample->quantity = 1;
        $sample->passed = 'сдал';
        $sample->accepted = 'принял';
        $sample->save();

        return redirect('/samples/' . $sample->id . '/edit');
    }
}
