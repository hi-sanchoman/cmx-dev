<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProtocolRequest;
use App\Http\Requests\UpdateProtocolRequest;
use App\Repositories\ProtocolRepository;
use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use Flash;
use Response;
use App\Models\Client;
use App\Models\Protocol;
use App\Models\Sample;
use App\Models\Field;
use Carbon\Carbon;

class ProtocolController extends AppBaseController
{
    /** @var  ProtocolRepository */
    private $protocolRepository;

    public function __construct(ProtocolRepository $protocolRepo)
    {
        $this->protocolRepository = $protocolRepo;
    }

    /**
     * Display a listing of the Protocol.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $protocols = Protocol::with(['client'])->orderBy('client_id', 'ASC')->get();

        return view('protocols.index')
            ->with('protocols', $protocols);
    }

    /**
     * Show the form for creating a new Protocol.
     *
     * @return Response
     */
    public function create()
    {
        return view('protocols.create');
    }

    /**
     * Store a newly created Protocol in storage.
     *
     * @param CreateProtocolRequest $request
     *
     * @return Response
     */
    public function store(CreateProtocolRequest $request)
    {
        $input = $request->all();

        $protocol = $this->protocolRepository->create($input);

        Flash::success(__('messages.saved', ['model' => __('models/protocols.singular')]));

        return redirect(route('protocols.index'));
    }

    /**
     * Display the specified Protocol.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $protocol = $this->protocolRepository->find($id);

        if (empty($protocol)) {
            Flash::error(__('messages.not_found', ['model' => __('models/protocols.singular')]));

            return redirect(route('protocols.index'));
        }

        return view('protocols.show')->with('protocol', $protocol);
    }

    /**
     * Show the form for editing the specified Protocol.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $protocol = $this->protocolRepository->find($id);

        if (empty($protocol)) {
            Flash::error(__('messages.not_found', ['model' => __('models/protocols.singular')]));

            return redirect(route('protocols.index'));
        }

        return view('protocols.edit')->with('protocol', $protocol);
    }

    /**
     * Update the specified Protocol in storage.
     *
     * @param int $id
     * @param UpdateProtocolRequest $request
     *
     * @return Response
     */
    public function update($id, UpdateProtocolRequest $request)
    {
        $protocol = $this->protocolRepository->find($id);

        if (empty($protocol)) {
            Flash::error(__('messages.not_found', ['model' => __('models/protocols.singular')]));

            return redirect(route('protocols.index'));
        }

        $protocol = $this->protocolRepository->update($request->all(), $id);

        Flash::success(__('messages.updated', ['model' => __('models/protocols.singular')]));

        return redirect(route('protocols.index'));
    }

    /**
     * Remove the specified Protocol from storage.
     *
     * @param int $id
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function destroy($id)
    {
        $protocol = $this->protocolRepository->find($id);

        if (empty($protocol)) {
            Flash::error(__('messages.not_found', ['model' => __('models/protocols.singular')]));

            return redirect(route('protocols.index'));
        }

        $this->protocolRepository->delete($id);

        Flash::success(__('messages.deleted', ['model' => __('models/protocols.singular')]));

        return redirect(route('protocols.index'));
    }



    public function redirect(Request $request) {
        return redirect('/clients/' . $request->client_id . '/protocol');
    }



    public function prepare($id, $fieldId) {
        $protocol = Protocol::with(['client', 'client.fields'])->whereId($id)->firstOrfail();
        $client = $protocol->client;

        $field = Field::with(['polygon', 'polygon.points'])->whereId($fieldId)->firstOrfail();
        $points = $field->polygon->points;

        $pointsIds = [];
        foreach ($points as $point) {
            $pointsIds[] = $point->id;
        }

        $samples = Sample::whereIn('point_id', $pointsIds)->get();
        // dd($samples->toArray());

        if ($samples == null) {
            // no samples yet
            Flash::error('???? ???????????? ???????????????????? ??????????????????');
            return redirect()->route('protocols.index');
        }

        $firstSample = $samples->first();
        if ($firstSample->date_started == null || $firstSample->date_completed == null || $firstSample->result == null) {
            Flash::error('???? ???????????? ???????????????????? ??????????????????');
            return redirect()->route('protocols.index');
        }

        // dd($samples->first()->date_selected->format('m/d/Y'));

        return view('protocols.prepare', compact('protocol', 'client', 'field', 'points', 'samples'));
    }

    public function preparePost(Request $request) {
        $input = $request->all();
        // dd($input);

        return redirect('/protocols/' . $input['protocol_id'] . '/' . $input['field_id'] . '/prepare');
    }



    public function generateForClient(Request $request, $id) {
        $input = $request->all();
        // dd($input);

        $client = Client::with(['fields', 'fields.polygon', 'fields.polygon.points'])->findOrFail($id);
        $now = Carbon::now();

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);
        $phpWord->setDefaultParagraphStyle(['spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0), 'spacing' => 120, 'lineHeight' => 1.2,]);

        $phpWord->addFontStyle('h1', ['bold' => true, 'size' => 14]);
        $phpWord->addFontStyle('default', ['bold' => false, 'size' => 11]);
        $phpWord->addFontStyle('bold', ['bold' => true, 'size' => 11]);
        $phpWord->addFontStyle('top', ['bold' => false, 'size' => 11]);
        $phpWord->addFontStyle('top_bold', ['bold' => true, 'size' => 11]);
        $phpWord->addFontStyle('bottom', ['bold' => false, 'size' => 9]);
        $phpWord->addFontStyle('footer', ['bold' => false, 'size' => 10]);

        $phpWord->addParagraphStyle('center', ['align' => 'center', 'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0), 'spacing' => 120, 'lineHeight' => 1,]);

        $phpWord->addParagraphStyle('left', ['align' => 'left', 'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0), 'spacing' => 120, 'lineHeight' => 1,]);

        $phpWord->addParagraphStyle('right', ['align' => 'right', 'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0), 'spacing' => 120, 'lineHeight' => 1,]);
        
        // new section
        $section = $phpWord->addSection([
            'marginLeft' => 900, 
            'marginRight' => 600,
            'marginTop' => 300,
            'marginBottom' => 300
        ]);

        $fields = [];
        foreach ($client->fields as $field) {
            $fields[] = $field;
        }
        
        // header
        $this->_generateHeader($section, $input, $fields);

        // loop of results for each Point's sample
        foreach ($client->fields as $field) {
            $this->_generateForField($section, $field, $client, $input);
        }

        // footer
        $this->_generateFooter($section, $input);
    
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $filename = 'protocol_client_' . $client->id . '.docx';
        $objWriter->save('docs/' . $filename);

        return response()->download(public_path('docs/' . $filename), '???????????????? ?????????? ' . $client->khname . '.docx');
    }




    public function generate(Request $request, $id) {
        $input = $request->all();
        // dd($input);

        $protocol = Protocol::with(['client', 'client.fields'])->whereId($id)->firstOrfail();
        $client = $protocol->client;
        $field = Field::whereId($input['field_id'])->firstOrfail();

        $now = Carbon::now();

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);
        $phpWord->setDefaultParagraphStyle(['spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0), 'spacing' => 120, 'lineHeight' => 1.2,]);

        $phpWord->addFontStyle('h1', ['bold' => true, 'size' => 14]);
        $phpWord->addFontStyle('default', ['bold' => false, 'size' => 11]);
        $phpWord->addFontStyle('bold', ['bold' => true, 'size' => 11]);
        $phpWord->addFontStyle('top', ['bold' => false, 'size' => 11]);
        $phpWord->addFontStyle('top_bold', ['bold' => true, 'size' => 11]);
        $phpWord->addFontStyle('bottom', ['bold' => false, 'size' => 9]);
        $phpWord->addFontStyle('footer', ['bold' => false, 'size' => 10]);

        $phpWord->addParagraphStyle('center', ['align' => 'center', 'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0), 'spacing' => 120, 'lineHeight' => 1,]);

        $phpWord->addParagraphStyle('left', ['align' => 'left', 'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0), 'spacing' => 120, 'lineHeight' => 1,]);

        $phpWord->addParagraphStyle('right', ['align' => 'right', 'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0), 'spacing' => 120, 'lineHeight' => 1,]);
        
        // cell style
        $cellStyle = ['unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT, 'valign' => 'center'];

        // new section
        $section = $phpWord->addSection([
            'marginLeft' => 900, 
            'marginRight' => 600,
            'marginTop' => 300,
            'marginBottom' => 300
        ]);

        // header
        $this->_generateHeader($section, $input, $field);

        // loop of results for each Point's sample
        $this->_generateForField($section, $field, $client, $input);

        // footer
        $this->_generateFooter($section, $input);
    
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $filename = 'protocol_' . $protocol->id . '.docx';
        $objWriter->save('docs/' . $filename);

        return response()->download(public_path('docs/' . $filename), '???????????????? ' . $client->khname . ' - ???????? ???' . $field->num . '.docx');
    }


    private function _generateHeader($section, $input, $field) {
        $pointsIds = [];        

        if (is_array($field)) {
            foreach ($field as $f) {
                foreach ($f->polygon->points as $point) {
                    if ($point == null) continue;
                    $pointsIds[] = $point->id;
                }  
            }
        } else {
            foreach ($field->polygon->points as $point) {
                if ($point == null) continue;
                $pointsIds[] = $point->id;
            }    
        }

        
        // get samples
        $samples = Sample::with(['point', 'result'])->whereIn('point_id', $pointsIds)->orderBy('num', 'ASC')->get();

        $datesSelected = [];
        $datesReceived = [];

        foreach ($samples as $sample) {
            $selected = Carbon::createFromFormat('Y-m-d', $sample->date_selected->format('Y-m-d'))->format('d.m.Y');
            $received = Carbon::createFromFormat('Y-m-d', $sample->date_received->format('Y-m-d'))->format('d.m.Y');

            if (!isset($datesSelected[$selected]))
                $datesSelected[$selected] = $selected;

            if (!isset($datesReceived[$received]))
                $datesReceived[$received] = $received; 
        }

        // dd([$datesSelected, $datesReceived]);

        // header start
        $header = $section->createHeader();
        $header->addImage(public_path('img/protocol_bg.jpg'), [
            'width'         => 530,
            'height'        => 30,
            'marginTop'     => -1,
            'marginLeft'    => -1,
            'wrappingStyle' => 'behind'
        ]); 

        // top
        $section->addText();$section->addText();
        $table = $section->addTable(['unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT, 'width' => 100 * 50]);
        $table->addRow();
        
        // image        
        $leftCell = $table->addCell(20 * 50, ['unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT]);
        $leftCell->addImage(public_path('img/protocol_image_left.png', ['width' => 3, 'height' => 3]));

        // texts
        $middleCell = $table->addCell();
        $middleCell->addText('?????????????????????????? ??????????????????????', 'top_bold', 'center');
        $middleCell->addText('?????? "CemEX Engineering"', 'top_bold', 'center');
        $middleCell->addText('????, ??. ????????????, ????. ??????-????????????, ??. 30??, ????. 61', 'top', 'center');
        $middleCell->addText('e-mail: office@cemex.kz, lab@cemex.kz', 'top', 'center');
        $middleCell->addText('???????????????? ???????????????????????? KZ.T.02.2272', 'top', 'center');
        $middleCell->addText('???? 14 ?????????????? 2019 ??.', 'top', 'center');

        // logo
        $rightCell = $table->addCell(20 * 50, ['unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT]);
        $rightCell->addImage(public_path('img/protocol_image_right.jpg', ['width' => 50, 'height' => 50, 'align' => 'center']));

        // title
        $section->addText();$section->addText();
        $section->addText('???????????????? ?????????????????? ???????? ??????????', 'h1', 'center');
        $section->addText('???' . $input['num'] . '/?? ???? ' . Carbon::createFromFormat('Y-m-d', $input['protocol_date'])->format('d.m.Y'), 'h1', 'center');
        $section->addText();

        // list of params
        $section->addText('1. ????????????????????????, ?????????? ?????????????????? - ' . $input['client_khname'] . ';', ['lineHeight' => 1]);
        $section->addText('2. ???????????? ?????????????????? - ' . $input['object'] . ';', ['lineHeight' => 1]);
        
        $actStr = '';
        if ($input['act_num'] != null) {
            $actStr = '?????? ???????????? ???' . $input['act_num'];
        }

        // $section->addText('3. ???????? ???????????? - ' . Carbon::createFromFormat('Y-m-d', $input['date_selected'])->format('d.m.Y') . ';' . $actStr);
        $section->addText('3. ???????? ???????????? - ' . implode(',', $datesSelected) . ';' . $actStr);
        

        $section->addText('4. ???? ???? ?????????? ???????????? - ' . $input['nd_method'] . ';');
        $section->addText('5. ?????????? ???????????? ???????? - ' . $input['field_address'] . ';');
        $section->addText('6. ???????????????????? ???????? - ' . $input['points_num'] . ';');
        
        // $section->addText('7. ???????? ?????????????????????? ???????? ???? ?????????????????? - ' . Carbon::createFromFormat('Y-m-d', $input['date_received'])->format('d.m.Y') . ';');
        $section->addText('7. ???????? ?????????????????????? ???????? ???? ?????????????????? - ' . implode(',', $datesReceived) . ';');
        
        
        $section->addText('8. ???????? ???????????????????? ?????????????????? - ' . Carbon::createFromFormat('Y-m-d', $input['date_started'])->format('d.m.Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $input['date_completed'])->format('d.m.Y') . ';');
        
        $section->addText('9. ?????????? ???????????????????? ?????????????????? - ' . $input['cemex_address'] . ';');
        $section->addText('10. ???? ???? ?????????????????? - ' . $input['nd_product'] . ';');
        $section->addText('11. ???????? - ' . $input['goal'] . ';');
        $section->addText('12. ?????????????? ???????????????????? ??????????????????: ' . $input['conditions']);
    }


    private function _generateFooter($section, $input) {
        $section->addText();
        $section->addText();

        $section->addText('??????????????????????: ');
        $section->addText('????????????????????    _________________________________  ?????????????????? ??.??.', 'default', 'center');
        $section->addText('?????????????????? ????????????????????????    _____________________  ?????????????????? ??.??.', 'default', 'center');
        

        $section->addText();
        $section->addText();
        $section->addText();
        $section->addText();
        $section->addText("???????????????? ???????????????????????????????? ???????????? ???? ?????????????? (??????????) ???????????????????????? ????????????????????. ?????????????????? ?????? ???????????? ?????????????????????? ?????????????????? ??????????????????.\n?????????? ?????????????????? ?????? ???????????? ???? ?????? 'CemEX Engineering' ???? ??????????????????????????.", 'bottom', 'center');

        // footer
        $footer = $section->createFooter();
        $table = $footer->addTable(['unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT, 'width' => 100 * 50]);
        $table->addRow();
        $table->addCell()->addText('???????????????? ?????????????????? ???????? ?????????? ???' . $input['num'] . '/?? ???? ' . Carbon::createFromFormat('Y-m-d', $input['protocol_date'])->format('d.m.Y'), 'footer', 'left');

        $table->addCell()->addPreserveText("?????????? ??????????????: {NUMPAGES}. ???????????????? {PAGE} ???? {NUMPAGES}", 'footer', 'right');
    }



    private function _generateForField($section, $field, $client, $input) {
        // cell style
        $cellStyle = ['unit' => \PhpOffice\PhpWord\Style\Table::WIDTH_PERCENT, 'valign' => 'center'];

        $pointsIds = [];        

        foreach ($field->polygon->points as $point) {
            if ($point == null) continue;
            $pointsIds[] = $point->id;
        }

        $samples = Sample::with(['point', 'result'])->whereIn('point_id', $pointsIds)->orderBy('num', 'ASC')->get();

        foreach ($samples as $sample) {                
            if ($sample == null) continue;

            $result = $sample->result;
            if ($result == null) continue;

            // if ($result->humus == null) continue;

            $year = '';
            if ($sample->date_completed != null) {
                $year = $sample->date_completed->format('Y');
            }

            $section->addText();$section->addText();
            $section->addText('?????????? ?????????? - ' . $sample->num . '/??/' . $client->num . '/' . $year . ' (?????????? ' . $sample->point->num . ', ' . $field->cadnum . ', ' . $field->square . ' ????)', 'bold');

            $table = $section->addTable(['borderSize' => 1, 'borderColor' => '000000']);
            $table->addRow();
            $table->addCell(40 * 50, $cellStyle)->addText('???????????????????????? ??????????????????????', 'bold', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???????????????????? ??????????????????', 'bold', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???? ???? ?????????? ??????????????????', 'bold', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???????????????????? ???????????????????????? ???????????????? ?? ??????????, ?????? ????/???? (?????? ????????????????????????????)', 'bold', 'center');

            

            // humus
            $table->addRow();
            $table->addCell()->addText('???????????????????????? ???????????????? (??????????), %');
            $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->humus), 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???????? 26213-91', 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateHumus($result->humus), 'default', 'center');

            // ph
            $table->addRow();
            $table->addCell()->addText('???????????????????? ????????????????????, pH');
            $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->ph), 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???????? 26423-85', 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduatePh($result->ph), 'default', 'center');

            // no3
            $table->addRow();
            $table->addCell()->addText('???????? ?????????????????? (NO3), ????/????');
            $table->addCell()->addText($this->_format($result->no3), 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???????? 26423-85', 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateNo3($result->no3), 'default', 'center');

            // p
            $table->addRow();
            $table->addCell()->addText('???????????? (P) ??????????????????, ????/????');
            $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->p), 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???????? 26205-91', 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateP($result->p), 'default', 'center');

            // k
            $table->addRow();
            $table->addCell()->addText('?????????? (??) ??????????????????, ????/????');
            $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->k), 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???????? 26205-91', 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateK($result->k), 'default', 'center');

            // s
            $table->addRow();
            $table->addCell()->addText('???????? (S) ??????????????????, ????/????');
            $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->s), 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText('???????? 26490-85', 'default', 'center');
            $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateS($result->s), 'default', 'center');

            if ($input['quantity'] == 'full') {
                // b
                $table->addRow();
                $table->addCell()->addText('?????? (B) ??????????????????, ????/????');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->b), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? ?? 50688-94', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('-', 'default', 'center');

                // fe
                $table->addRow();
                $table->addCell()->addText('???????????? (Fe) ??????????????????, ????/????');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->fe), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? 27395-87', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('-', 'default', 'center');

                // mn
                $table->addRow();
                $table->addCell()->addText('???????????????? (Mn) ??????????????????, ????/????');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->mn), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? ?? 50685-94', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateMn($result->mn), 'default', 'center');

                // cu
                $table->addRow();
                $table->addCell()->addText('???????? (Cu) ??????????????????, ????/????');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->cu), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???? ???? ???????? ?? 50683-2008', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateCu($result->cu), 'default', 'center');

                // zn
                $table->addRow();
                $table->addCell()->addText('???????? (Zn) ??????????????????, ????/????');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->zn), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? ?? 50686-94', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateZn($result->zn), 'default', 'center');

                // na
                $table->addRow();
                $table->addCell()->addText('???????????? (Na) ????????????????, ??????????/100 ??');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->na), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? 26950-86', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('-', 'default', 'center');

                // calcium
                $table->addRow();
                $table->addCell()->addText('?????????????? ????????????????, ??????????/100 ??');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->calcium), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? 26428-85', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateCalcium($result->calcium), 'default', 'center');

                // magnesium
                $table->addRow();
                $table->addCell()->addText('???????????? ????????????????, ??????????/100 ??');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->magnesium), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? 26428-85', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateMagnesium($result->magnesium), 'default', 'center');

                // salinity
                $table->addRow();
                $table->addCell()->addText('?????????? ????????????????????????, ??????/????');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->salinity), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? 27753.4-88', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateSalinity($result->salinity), 'default', 'center');

                // absorbed_sum
                $table->addRow();
                $table->addCell()->addText('?????????? ?????????????????????? ??????????????????, ??????????/100 ??');
                $table->addCell(20 * 50, $cellStyle)->addText($this->_format($result->absorbed_sum), 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText('???????? 27821-88', 'default', 'center');
                $table->addCell(20 * 50, $cellStyle)->addText(Sample::graduateAbsorbedSum($result->absorbed_sum), 'default', 'center');
            }
        }
    }



    private function _format($value) {
        if ($value < 0) {
            return '??.??.';
        }

        return round($value * 10000) / 10000;
    }
}
