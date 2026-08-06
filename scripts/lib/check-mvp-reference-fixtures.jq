def round_half_up:
  (. + 0.5) | floor;

def assertion($condition; $fixture_id; $message):
  if $condition then empty else "\($fixture_id): \($message)" end;

def change_kind($current; $baseline):
  if $current == null or $baseline == null then "NotComparable"
  elif $current == $baseline then "Stable"
  elif $baseline == 0 and $current > 0 then "Started"
  elif $baseline > 0 and $current == 0 then "Stopped"
  elif $current > $baseline then "Increased"
  else "Decreased"
  end;

def scalar_metric($unit; $currency; $current; $baseline):
  if $current == null then
    {state: "NoData", unit: $unit, change_kind: "NotComparable"}
  else
    ({
      state: "Available",
      unit: $unit,
      exact_value: $current,
      change_kind: change_kind($current; $baseline)
    } + if $unit == "Money" then {currency: $currency} else {} end)
  end;

def ratio_metric($current; $baseline; $numerator; $denominator; $sample_size):
  if $current == null then
    {state: "NoData", unit: "Ratio", change_kind: "NotComparable"}
  else
    {
      state: "Available",
      unit: "Ratio",
      exact_value: $current,
      numerator: $numerator,
      denominator: $denominator,
      sample_size: $sample_size,
      change_kind: change_kind($current; $baseline)
    }
  end;

def duration_metric($current; $baseline; $sample_size):
  if $current == null then
    {state: "NoData", unit: "Duration", change_kind: "NotComparable"}
  else
    {
      state: "Available",
      unit: "Duration",
      exact_value: $current,
      sample_size: $sample_size,
      change_kind: change_kind($current; $baseline)
    }
  end;

def quote_denominator($period):
  if $period == null then null
  else $period.accepted + $period.rejected + $period.expired
  end;

def quote_rate($period):
  quote_denominator($period) as $denominator
  | if $denominator == null or $denominator == 0 then null
    else $period.accepted / $denominator
    end;

def quote_response($period):
  if $period == null or $period.response_sample_size == 0 then null
  else $period.response_seconds_total / $period.response_sample_size
  end;

def settlement_rate($period):
  if $period == null or $period.settled_count == 0 then null
  else $period.on_time_count / $period.settled_count
  end;

def settlement_duration($period):
  if $period == null or $period.settled_count == 0 then null
  else $period.duration_seconds_total / $period.settled_count
  end;

def collection_share($period):
  if $period == null or $period.total_amount_minor <= 0 then null
  else ([ $period.by_client_minor[] ] | max) / $period.total_amount_minor
  end;

def calculated_metrics($root; $case):
  $case.source_fact_summary as $source
  | $source.quote_decisions_rolling_90_days.current as $quote_current
  | $source.quote_decisions_rolling_90_days.baseline as $quote_baseline
  | $source.settlements_rolling_90_days.current as $settlement_current
  | $source.settlements_rolling_90_days.baseline as $settlement_baseline
  | $source.collections_rolling_365_days.current as $collection_current
  | $source.collections_rolling_365_days.baseline as $collection_baseline
  | {
      "analytics.pipeline.open-amount": scalar_metric(
        "Money"; $root.currency;
        $source.pipeline.current_amount_minor;
        $source.pipeline.baseline_amount_minor
      ),
      "analytics.quotes.pending-amount": scalar_metric(
        "Money"; $root.currency;
        $source.pending_quotes.current_amount_minor;
        $source.pending_quotes.baseline_amount_minor
      ),
      "analytics.quotes.acceptance-rate": ratio_metric(
        quote_rate($quote_current);
        quote_rate($quote_baseline);
        ($quote_current.accepted // null);
        quote_denominator($quote_current);
        quote_denominator($quote_current)
      ),
      "analytics.quotes.average-response-time": duration_metric(
        quote_response($quote_current);
        quote_response($quote_baseline);
        ($quote_current.response_sample_size // null)
      ),
      "analytics.billing.net-invoiced-amount": scalar_metric(
        "Money"; $root.currency;
        $source.net_invoiced_rolling_30_days.current_amount_minor;
        $source.net_invoiced_rolling_30_days.baseline_amount_minor
      ),
      "analytics.billing.collected-amount": scalar_metric(
        "Money"; $root.currency;
        $source.collected_rolling_30_days.current_amount_minor;
        $source.collected_rolling_30_days.baseline_amount_minor
      ),
      "analytics.receivables.outstanding-amount": scalar_metric(
        "Money"; $root.currency;
        ($source.receivables.current.outstanding_amount_minor // null);
        ($source.receivables.baseline.outstanding_amount_minor // null)
      ),
      "analytics.receivables.overdue-amount": scalar_metric(
        "Money"; $root.currency;
        ($source.receivables.current.overdue_amount_minor // null);
        ($source.receivables.baseline.overdue_amount_minor // null)
      ),
      "analytics.receivables.overdue-count": scalar_metric(
        "Count"; $root.currency;
        ($source.receivables.current.overdue_count // null);
        ($source.receivables.baseline.overdue_count // null)
      ),
      "analytics.receivables.due-soon-amount": scalar_metric(
        "Money"; $root.currency;
        ($source.receivables.current.due_soon_amount_minor // null);
        ($source.receivables.baseline.due_soon_amount_minor // null)
      ),
      "analytics.payments.average-time-to-payment": duration_metric(
        settlement_duration($settlement_current);
        settlement_duration($settlement_baseline);
        ($settlement_current.settled_count // null)
      ),
      "analytics.payments.on-time-rate": ratio_metric(
        settlement_rate($settlement_current);
        settlement_rate($settlement_baseline);
        ($settlement_current.on_time_count // null);
        ($settlement_current.settled_count // null);
        ($settlement_current.settled_count // null)
      ),
      "analytics.clients.top-collection-share": ratio_metric(
        collection_share($collection_current);
        collection_share($collection_baseline);
        (if $collection_current == null then null else ([ $collection_current.by_client_minor[] ] | max) end);
        ($collection_current.total_amount_minor // null);
        (if $collection_current == null then null else ($collection_current.by_client_minor | length) end)
      )
    };

def validate_analytics($root; $case):
  calculated_metrics($root; $case) as $calculated
  | $case.expected.analytics.metrics as $expected
  | $case.expected.analytics.snapshot as $snapshot
  | (if $snapshot.source_lag_seconds <= $root.snapshot_profile.current_lag_threshold_seconds
     then "Current"
     elif $snapshot.source_lag_seconds <= $root.snapshot_profile.maximum_accepted_lag_seconds
     then "Lagging"
     else "Unavailable"
     end) as $freshness
  | assertion(
      $snapshot.profile_version == $root.snapshot_profile.version
      and $snapshot.completeness == $root.snapshot_profile.required_completeness
      and $snapshot.source_lag_seconds <= $root.snapshot_profile.maximum_accepted_lag_seconds
      and $snapshot.freshness == $freshness;
      $case.fixture_id;
      "version, complétude ou fraîcheur du snapshot incorrecte"
    ),
    ($calculated
     | to_entries[]
     | . as $entry
     | assertion(
         $expected[$entry.key] | contains($entry.value);
         $case.fixture_id;
         "observation Analytics incorrecte pour \($entry.key)"
       ));

def unavailable_component($reason):
  {status: "Unavailable", reason: $reason};

def available_component($score):
  {status: "Available", score: $score};

def evolution_score($current; $baseline; $zero_score; $reject_negative):
  if $current == null or $baseline == null then null
  elif $reject_negative and $current < 0 then null
  elif $current > 0 and $baseline == 0 then 80
  elif $current == 0 and $baseline > 0 then 0
  elif $current == 0 and $baseline == 0 then $zero_score
  else (($current - $baseline) / $baseline) as $delta
    | if $delta >= 0.1 then 100
      elif $delta >= 0 then 80
      elif $delta > -0.1 then 60
      elif $delta > -0.3 then 35
      else 10
      end
  end;

def acceptance_component($source):
  $source.quote_decisions_rolling_90_days.current as $period
  | quote_denominator($period) as $sample
  | if $sample == null or $sample == 0 then unavailable_component("NoData")
    elif $sample < 3 then unavailable_component("SampleBelowMinimum")
    else quote_rate($period) as $rate
      | available_component(
          if $rate >= 0.7 then 100
          elif $rate >= 0.5 then 80
          elif $rate >= 0.3 then 55
          elif $rate >= 0.15 then 30
          else 10
          end
        )
    end;

def pipeline_component($source):
  evolution_score(
    $source.pipeline.current_amount_minor;
    $source.pipeline.baseline_amount_minor;
    10;
    false
  ) as $score
  | if $score == null then unavailable_component("NoData")
    else available_component($score)
    end;

def billing_component($current; $baseline):
  evolution_score($current; $baseline; 20; true) as $score
  | if $score == null then unavailable_component("NoData")
    else available_component($score)
    end;

def overdue_component($source):
  $source.receivables.current as $current
  | if $current == null then unavailable_component("NoData")
    elif $current.overdue_amount_minor > 0 and $current.outstanding_amount_minor == 0 then
      unavailable_component("InconsistentReceivables")
    elif $current.outstanding_amount_minor == 0 and $current.overdue_amount_minor == 0 then
      available_component(100)
    elif $current.overdue_amount_minor == 0 then available_component(100)
    else ($current.overdue_amount_minor / $current.outstanding_amount_minor) as $ratio
      | available_component(
          if $ratio <= 0.1 then 80
          elif $ratio <= 0.25 then 55
          elif $ratio <= 0.5 then 25
          else 0
          end
        )
    end;

def on_time_component($source):
  $source.settlements_rolling_90_days.current as $period
  | if $period == null or $period.settled_count == 0 then unavailable_component("NoData")
    elif $period.settled_count < 3 then unavailable_component("SampleBelowMinimum")
    else settlement_rate($period) as $rate
      | available_component(
          if $rate >= 0.9 then 100
          elif $rate >= 0.75 then 80
          elif $rate >= 0.5 then 50
          elif $rate >= 0.25 then 25
          else 0
          end
        )
    end;

def diversification_component($source):
  collection_share($source.collections_rolling_365_days.current) as $share
  | if $share == null then unavailable_component("NoData")
    else available_component(
      if $share <= 0.35 then 100
      elif $share <= 0.5 then 75
      elif $share <= 0.7 then 45
      elif $share <= 0.9 then 20
      else 5
      end
    )
    end;

def calculated_components($case):
  if $case.expected.analytics.snapshot.freshness != "Current"
     or $case.expected.analytics.snapshot.completeness != "Complete" then
    {
      quote_acceptance: unavailable_component("StaleSnapshot"),
      pipeline_evolution: unavailable_component("StaleSnapshot"),
      net_invoiced_evolution: unavailable_component("StaleSnapshot"),
      collected_evolution: unavailable_component("StaleSnapshot"),
      overdue_load: unavailable_component("StaleSnapshot"),
      on_time_payment: unavailable_component("StaleSnapshot"),
      top_client_share: unavailable_component("StaleSnapshot")
    }
  else
    $case.source_fact_summary as $source
    | {
      quote_acceptance: acceptance_component($source),
      pipeline_evolution: pipeline_component($source),
      net_invoiced_evolution: billing_component(
        $source.net_invoiced_rolling_30_days.current_amount_minor;
        $source.net_invoiced_rolling_30_days.baseline_amount_minor
      ),
      collected_evolution: billing_component(
        $source.collected_rolling_30_days.current_amount_minor;
        $source.collected_rolling_30_days.baseline_amount_minor
      ),
      overdue_load: overdue_component($source),
      on_time_payment: on_time_component($source),
      top_client_share: diversification_component($source)
    }
  end;

def calculated_factor($components; $parts; $minimum):
  [ $parts[] | select($components[.key].status == "Available") ] as $available
  | ($available | map(.weight) | add // 0) as $coverage
  | if $coverage < $minimum then
      {status: "Unavailable", coverage_percent: $coverage}
    else
      {
        status: "Available",
        score: (($available | map(.weight * $components[.key].score) | add) / $coverage | round_half_up),
        coverage_percent: $coverage
      }
    end;

def factor_weights:
  [
    {key: "CommercialMomentum", weight: 30},
    {key: "BillingMomentum", weight: 20},
    {key: "ReceivablesDiscipline", weight: 35},
    {key: "ClientDiversification", weight: 15}
  ];

def calculated_factors($components):
  {
    CommercialMomentum: calculated_factor(
      $components;
      [{key: "quote_acceptance", weight: 60}, {key: "pipeline_evolution", weight: 40}];
      40
    ),
    BillingMomentum: calculated_factor(
      $components;
      [{key: "net_invoiced_evolution", weight: 50}, {key: "collected_evolution", weight: 50}];
      50
    ),
    ReceivablesDiscipline: calculated_factor(
      $components;
      [{key: "overdue_load", weight: 60}, {key: "on_time_payment", weight: 40}];
      60
    ),
    ClientDiversification: calculated_factor(
      $components;
      [{key: "top_client_share", weight: 100}];
      100
    )
  };

def health_band($score):
  if $score == null then null
  elif $score >= 80 then "Strong"
  elif $score >= 60 then "Stable"
  elif $score >= 40 then "Watch"
  elif $score >= 20 then "AtRisk"
  else "Critical"
  end;

def risk_factor($risk_key):
  if $risk_key == "OverdueExposureRisk" then "ReceivablesDiscipline"
  elif $risk_key == "ClientConcentrationRisk" then "ClientDiversification"
  elif $risk_key == "CommercialMomentumRisk" then "CommercialMomentum"
  elif $risk_key == "BillingMomentumRisk" then "BillingMomentum"
  else null
  end;

def decline_signal($current; $baseline):
  if $current == null or $baseline == null or $baseline <= 0 then null
  elif $current == 0 then {strong: true}
  else (($current - $baseline) / $baseline) as $delta
    | if $delta <= -0.3 then {strong: true}
      elif $delta <= -0.1 then {strong: false}
      else null
      end
  end;

def calculated_risks($case):
  if $case.expected.analytics.snapshot.freshness != "Current"
     or $case.expected.analytics.snapshot.completeness != "Complete" then []
  else
    $case.source_fact_summary as $source
    | $source.receivables.current as $receivables
    | collection_share($source.collections_rolling_365_days.current) as $share
    | decline_signal($source.pipeline.current_amount_minor; $source.pipeline.baseline_amount_minor) as $pipeline_signal
    | quote_denominator($source.quote_decisions_rolling_90_days.current) as $quote_sample
    | (if $quote_sample != null and $quote_sample >= 3 and quote_rate($source.quote_decisions_rolling_90_days.current) < 0.3
       then {strong: false} else null end) as $acceptance_signal
    | decline_signal(
        $source.net_invoiced_rolling_30_days.current_amount_minor;
        $source.net_invoiced_rolling_30_days.baseline_amount_minor
      ) as $net_signal
    | decline_signal(
        $source.collected_rolling_30_days.current_amount_minor;
        $source.collected_rolling_30_days.baseline_amount_minor
      ) as $collected_signal
    | (
        if $receivables != null and $receivables.overdue_amount_minor > 0 then
          ($receivables.overdue_amount_minor / $receivables.outstanding_amount_minor) as $ratio
          | [{
              risk_key: "OverdueExposureRisk",
              severity: (
                if $ratio <= 0.1 then "Low"
                elif $ratio <= 0.25 then "Medium"
                elif $ratio <= 0.5 then "High"
                else "Critical"
                end
              )
            }]
        else [] end
        + if $share != null and $share > 0.5 then
            [{
              risk_key: "ClientConcentrationRisk",
              severity: (if $share <= 0.7 then "Medium" elif $share <= 0.9 then "High" else "Critical" end)
            }]
          else [] end
        + if $pipeline_signal != null or $acceptance_signal != null then
            ([ $pipeline_signal, $acceptance_signal ] | map(select(. != null))) as $signals
            | [{
                risk_key: "CommercialMomentumRisk",
                severity: (if ($signals | any(.strong)) or ($signals | length) == 2 then "High" else "Medium" end)
              }]
          else [] end
        + if $net_signal != null or $collected_signal != null then
            ([ $net_signal, $collected_signal ] | map(select(. != null))) as $signals
            | ([ $signals[] | select(.strong) ] | length) as $strong_count
            | [{
                risk_key: "BillingMomentumRisk",
                severity: (if $strong_count == 2 then "Critical" elif $strong_count == 1 then "High" else "Medium" end)
              }]
          else [] end
      )
  end;

def calculated_health($case):
  calculated_components($case) as $components
  | calculated_factors($components) as $factors
  | factor_weights as $weights
  | ([ $weights[] | select($factors[.key].status == "Available") | .weight ] | add // 0) as $coverage
  | (if $coverage >= 70 then
       ([ $weights[] | select($factors[.key].status == "Available") | .weight * $factors[.key].score ] | add)
       / $coverage | round_half_up
     else null end) as $overall
  | (if $overall == null then null
     else
       [ $weights[]
         | select($factors[.key].status == "Available")
         | {
             factor_key: .key,
             deficit_contribution: (.weight * (100 - $factors[.key].score) / 100),
             original_weight: .weight
           }
       ]
       | sort_by([-.deficit_contribution, -.original_weight, .factor_key])
       | .[0]
       | del(.original_weight)
     end) as $attention
  | {
      assessment_status: (if $overall == null then "InsufficientData" else "Available" end),
      reliability: (
        if $overall == null then "Insufficient"
        elif ([ $components[] | select(.status == "Available") ] | length) == 7 then "Reliable"
        else "Limited"
        end
      ),
      components: $components,
      factors: $factors,
      global_coverage_percent: $coverage,
      overall_score: $overall,
      health_band: health_band($overall),
      primary_attention: $attention,
      risks: calculated_risks($case)
    };

def validate_health($case):
  calculated_health($case) as $calculated
  | assertion(
      $case.expected.business_health | contains($calculated);
      $case.fixture_id;
      "résultat Business Health différent du recalcul de la politique 1.0"
    );

def rule_spec($rule_key):
  if $rule_key == "CollectOverdueInvoices" then
    {recommendation_key: "advisor.collect-overdue-invoices", action_module: "Billing", route_key: "OverdueInvoices", effort: "Small", validity_days: 7, precedence: 1}
  elif $rule_key == "ReduceClientConcentration" then
    {recommendation_key: "advisor.reduce-client-concentration", action_module: "CRM", route_key: "NewOpportunity", effort: "Medium", validity_days: 30, precedence: 2}
  elif $rule_key == "RebuildCommercialPipeline" then
    {recommendation_key: "advisor.rebuild-commercial-pipeline", action_module: "CRM", route_key: "NewOpportunity", effort: "Medium", validity_days: 14, precedence: 3}
  elif $rule_key == "RestoreBillingMomentum" then
    {recommendation_key: "advisor.restore-billing-momentum", action_module: "Billing", route_key: "RecentInvoices", effort: "Small", validity_days: 14, precedence: 4}
  elif $rule_key == "AddressPrimaryAttention" then
    {recommendation_key: "advisor.address-primary-attention", validity_days: 14, precedence: 5}
  else null
  end;

def risk_rule($risk_key):
  if $risk_key == "OverdueExposureRisk" then "CollectOverdueInvoices"
  elif $risk_key == "ClientConcentrationRisk" then "ReduceClientConcentration"
  elif $risk_key == "CommercialMomentumRisk" then "RebuildCommercialPipeline"
  elif $risk_key == "BillingMomentumRisk" then "RestoreBillingMomentum"
  else null
  end;

def impact_value($level):
  if $level == "Major" then 100
  elif $level == "Significant" then 65
  elif $level == "Moderate" then 35
  elif $level == "Minor" then 10
  else 0
  end;

def urgency_value($level):
  if $level == "Immediate" then 100
  elif $level == "Today" then 75
  elif $level == "ThisWeek" then 50
  elif $level == "NoDeadline" then 20
  else 0
  end;

def confidence_value($level):
  if $level == "High" then 100
  elif $level == "Moderate" then 60
  elif $level == "Low" then 20
  else 0
  end;

def ease_value($effort):
  if $effort == "Small" then 100
  elif $effort == "Medium" then 60
  elif $effort == "Large" then 20
  else 0
  end;

def recommendation_rank($recommendation):
  (
      0.4 * impact_value($recommendation.impact)
    + 0.3 * urgency_value($recommendation.urgency)
    + 0.2 * confidence_value($recommendation.confidence)
    + 0.1 * ease_value($recommendation.effort)
  ) | round_half_up;

def recommendation_priority($score):
  if $score >= 85 then "Critical"
  elif $score >= 65 then "High"
  elif $score >= 40 then "Medium"
  else "Low"
  end;

def priority_order($priority):
  if $priority == "Critical" then 4
  elif $priority == "High" then 3
  elif $priority == "Medium" then 2
  else 1
  end;

def expected_rule_keys($case):
  if $case.expected.advisor.source_eligibility != "Eligible" then []
  else
    [ $case.expected.business_health.risks[] | risk_rule(.risk_key) ] as $risk_rules
    | $case.expected.business_health.primary_attention.factor_key as $attention_factor
    | ([ $case.expected.business_health.risks[] | risk_factor(.risk_key) ] | index($attention_factor)) as $same_factor_risk
    | ($risk_rules
       + if $attention_factor != null
            and $case.expected.business_health.health_band != "Strong"
            and $same_factor_risk == null
         then ["AddressPrimaryAttention"] else [] end)
  end;

def validate_recommendation($root; $case; $recommendation):
  rule_spec($recommendation.rule_key) as $spec
  | recommendation_rank($recommendation) as $rank
  | (
      ($root.as_of | fromdateiso8601)
      + ($spec.validity_days * 86400)
      | todateiso8601
    ) as $valid_until
  | assertion($spec != null; $case.fixture_id; "RuleKey Advisor inconnue: \($recommendation.rule_key)"),
    assertion($recommendation.recommendation_key == $spec.recommendation_key; $case.fixture_id; "RecommendationKey non canonique pour \($recommendation.rule_key)"),
    assertion(
      $recommendation.rule_key == "AddressPrimaryAttention"
      or ($recommendation.action_module == $spec.action_module and $recommendation.route_key == $spec.route_key and $recommendation.effort == $spec.effort);
      $case.fixture_id;
      "action ou effort non canonique pour \($recommendation.rule_key)"
    ),
    assertion($recommendation.rank_score == $rank; $case.fixture_id; "rang Advisor incorrect pour \($recommendation.rule_key)"),
    assertion($recommendation.priority == recommendation_priority($rank); $case.fixture_id; "Priority Advisor incorrecte pour \($recommendation.rule_key)"),
    assertion($recommendation.valid_until == $valid_until; $case.fixture_id; "ValidUntil incorrect pour \($recommendation.rule_key)"),
    assertion(
      $recommendation.confidence == (if $case.expected.business_health.reliability == "Reliable" then "High" else "Moderate" end);
      $case.fixture_id;
      "confiance Advisor incohérente avec la fiabilité"
    );

def validate_advisor($root; $case):
  expected_rule_keys($case) as $expected_rules
  | [ $case.expected.advisor.recommendations[].rule_key ] as $actual_rules
  | ($case.expected.advisor.recommendations | sort_by([
      -priority_order(.priority),
      -.rank_score,
      .valid_until,
      rule_spec(.rule_key).precedence,
      .recommendation_key
    ])) as $sorted
  | assertion(
      ($case.expected.business_health.assessment_status == "Available") == ($case.expected.advisor.source_eligibility == "Eligible");
      $case.fixture_id;
      "éligibilité Advisor incohérente avec Business Health"
    ),
    assertion(($actual_rules | sort) == ($expected_rules | sort); $case.fixture_id; "catalogue des règles Advisor différent des déclencheurs"),
    assertion(($case.expected.advisor.recommendations | length) <= 3; $case.fixture_id; "plus de trois Recommendations publiées"),
    assertion($case.expected.advisor.recommendations == $sorted; $case.fixture_id; "ordre total Advisor incorrect"),
    assertion(
      $case.expected.advisor.primary_recommendation_key == ($case.expected.advisor.recommendations[0].recommendation_key // null);
      $case.fixture_id;
      "PrimaryRecommendation différente du premier élément"
    ),
    ($case.expected.advisor.recommendations[] | validate_recommendation($root; $case; .));

def validate_notifications($case):
  $case.expected.notifications as $notifications
  | $case.expected.advisor.recommendations[0] as $primary
  | assertion(
      if $case.expected.advisor.source_eligibility != "Eligible" then
        $notifications.plan_decision == "SourceIgnored" and ($notifications.channels | length) == 0
      elif $primary == null then
        $notifications.plan_decision == "NoPrimaryRecommendation" and ($notifications.channels | length) == 0
      else true
      end;
      $case.fixture_id;
      "plan Notifications incohérent avec l'overview Advisor"
    ),
    assertion(
      if ($notifications.channels | index("Email")) != null then
        ($primary.priority == "High" or $primary.priority == "Critical")
        and $notifications.email_mode == "ImportantOnly"
      else true
      end;
      $case.fixture_id;
      "canal Email créé sans priorité ou consentement éligible"
    ),
    assertion(
      if $notifications.email_delivery_state == "Accepted" then
        $notifications.provider_submission_count == 1
        and $notifications.audience_at_dispatch == "Authorized"
        and $notifications.endpoint == "VerifiedOpaqueReference"
        and $notifications.frequency == "Available"
        and $notifications.email_content_class == "MinimalPriorityAvailable"
      else $notifications.provider_submission_count == 0
      end;
      $case.fixture_id;
      "effet fournisseur Notifications incohérent"
    ),
    assertion(
      if $notifications.audience_at_dispatch == "Revoked" then
        $notifications.notification_state == "Resolved"
        and $notifications.email_delivery_state == "Cancelled"
        and $notifications.provider_submission_count == 0
      else true
      end;
      $case.fixture_id;
      "révocation non appliquée avant dispatch"
    );

if $section == "analytics" then
  . as $root | $root.cases[] | validate_analytics($root; .)
elif $section == "health" then
  .cases[] | validate_health(.)
elif $section == "advisor" then
  . as $root | $root.cases[] | validate_advisor($root; .)
elif $section == "notifications" then
  .cases[] | validate_notifications(.)
else
  error("unknown validation section")
end
