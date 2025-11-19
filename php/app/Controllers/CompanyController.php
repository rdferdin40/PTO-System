<?php
/**
 * Company Controller
 * Company settings management (admin only)
 */

class CompanyController extends Controller
{
    /**
     * Edit company settings
     */
    public function edit(Request $request): Response
    {
        $this->requireAdmin();

        $user = $this->getUser();

        require_once APP_PATH . '/Models/Company.php';
        $company = Company::find($user['company_id']);

        return $this->view('company/edit', [
            'title' => 'Company Settings',
            'company' => $company,
        ]);
    }

    /**
     * Update company settings
     */
    public function update(Request $request): Response
    {
        $this->requireAdmin();
        $this->validateCsrf($request);

        $user = $this->getUser();

        require_once APP_PATH . '/Models/Company.php';

        $updateData = [
            'name' => $request->input('name'),
            'country' => $request->input('country'),
            'timezone' => $request->input('timezone'),
            'date_format' => $request->input('date_format'),
            'carry_over' => (int)$request->input('carry_over', 0),
            'share_all_absences' => $request->input('share_all_absences') ? 1 : 0,
            'is_team_view_hidden' => $request->input('is_team_view_hidden') ? 1 : 0,
            'accrued_adjustment_enabled' => $request->input('accrued_adjustment_enabled') ? 1 : 0,
            'payroll_close_time' => (int)$request->input('payroll_close_time', 10),
        ];

        Company::updateById($user['company_id'], $updateData);

        Session::flash('success', 'Company settings updated successfully');
        return $this->redirect('/settings/company');
    }
}
